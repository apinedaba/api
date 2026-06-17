<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class DeepSeekExerciseService
{
    public function generate(array $clinicalContext, array $requestContext): array
    {
        $apiKey = config('services.deepseek.api_key');

        if (! $apiKey) {
            throw new RuntimeException('DeepSeek no esta configurado.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(config('services.deepseek.timeout', 35))
            ->post(rtrim(config('services.deepseek.base_url'), '/') . '/chat/completions', [
                'model' => config('services.deepseek.model', 'deepseek-v4-flash'),
                'messages' => $this->messages($clinicalContext, $requestContext),
                'temperature' => 0.35,
                'max_tokens' => $this->maxTokens($requestContext),
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            report(new RuntimeException('DeepSeek error: ' . $response->body()));
            throw new RuntimeException('No se pudo generar la sugerencia con IA.');
        }

        $content = $this->normalizeContent(data_get($response->json(), 'choices.0.message.content'));
        $decoded = $this->decodeJsonContent($content);

        if (! is_array($decoded)) {
            Log::warning('DeepSeek exercise response was not valid JSON.', [
                'model' => data_get($response->json(), 'model', config('services.deepseek.model')),
                'finish_reason' => data_get($response->json(), 'choices.0.finish_reason'),
                'usage' => data_get($response->json(), 'usage'),
                'content_preview' => Str::limit($content, 600, ''),
            ]);

            throw new RuntimeException('DeepSeek devolvio una respuesta invalida.');
        }

        return [
            'summary' => Str::limit((string) Arr::get($decoded, 'summary', ''), 500, ''),
            'activities' => $this->normalizeActivities(Arr::get($decoded, 'activities', [])),
            'quickIdeas' => $this->normalizeStrings(Arr::get($decoded, 'quickIdeas', []), 6, 180),
            'safetyNotes' => $this->normalizeStrings(Arr::get($decoded, 'safetyNotes', []), 5, 220),
            'tokenUsage' => data_get($response->json(), 'usage'),
            'model' => data_get($response->json(), 'model', config('services.deepseek.model')),
        ];
    }

    private function maxTokens(array $requestContext): int
    {
        $configuredMax = (int) config('services.deepseek.max_tokens', 2600);
        $quantity = max(1, min(5, (int) Arr::get($requestContext, 'quantity', 3)));
        $estimated = 650 + ($quantity * 360);

        return max(1200, min($configuredMax, $estimated));
    }

    private function normalizeContent($content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (is_array($content)) {
            return collect($content)
                ->map(function ($part) {
                    if (is_string($part)) {
                        return $part;
                    }

                    return data_get($part, 'text') ?: data_get($part, 'content') ?: '';
                })
                ->filter()
                ->implode("\n");
        }

        return '';
    }

    private function decodeJsonContent(string $content): ?array
    {
        $content = trim($content);

        if ($content === '') {
            return null;
        }

        $candidates = [
            $content,
            preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content),
            $this->extractFirstJsonObject($content),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $decoded = json_decode(trim($candidate), true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function extractFirstJsonObject(string $content): ?string
    {
        $start = strpos($content, '{');

        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($content);

        for ($index = $start; $index < $length; $index++) {
            $char = $content[$index];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;
                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '{') {
                $depth++;
            }

            if ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($content, $start, $index - $start + 1);
                }
            }
        }

        return null;
    }

    private function messages(array $clinicalContext, array $requestContext): array
    {
        $mode = Arr::get($requestContext, 'mode', 'activities');
        $focus = Arr::get($requestContext, 'focus', '');

        return [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'Eres un asistente clinico para psicologos de MindMeet.',
                    'Genera sugerencias de actividades terapeuticas, no diagnosticos ni indicaciones medicas.',
                    'Privacidad: trabaja solo con el contexto anonimo recibido. No pidas ni infieras nombre, correo, telefono, direccion o identidad.',
                    'Seguridad: si hay senales de crisis, autolesion, violencia o riesgo, prioriza derivacion, plan de seguridad y supervision profesional.',
                    'Las propuestas deben ser revisadas y adaptadas por el psicologo antes de aplicarse.',
                    'Responde solo JSON valido con las llaves: summary, activities, quickIdeas, safetyNotes.',
                    'summary maximo 45 palabras.',
                    'activities debe ser un arreglo de objetos con: title, objective, steps, duration, materials, homePractice, cautions.',
                    'Cada objective, homePractice y cautions maximo 22 palabras. steps debe tener 3 a 4 pasos breves.',
                    'Usa espanol claro y muy compacto. Evita explicaciones largas para optimizar tokens.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'tipo_solicitud' => $mode,
                    'enfoque_rapido' => $focus,
                    'preferencias' => [
                        'formato' => Arr::get($requestContext, 'format', 'sesion_y_tarea'),
                        'duracion_minutos' => Arr::get($requestContext, 'duration', '10-20'),
                        'cantidad' => Arr::get($requestContext, 'quantity', 3),
                    ],
                    'contexto_clinico_anonimo' => $clinicalContext,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];
    }

    private function normalizeActivities($activities): array
    {
        if (! is_array($activities)) {
            return [];
        }

        return collect($activities)
            ->take(5)
            ->map(function ($activity) {
                return [
                    'title' => Str::limit((string) Arr::get($activity, 'title', 'Actividad sugerida'), 100, ''),
                    'objective' => Str::limit((string) Arr::get($activity, 'objective', ''), 260, ''),
                    'steps' => $this->normalizeStrings(Arr::get($activity, 'steps', []), 6, 220),
                    'duration' => Str::limit((string) Arr::get($activity, 'duration', ''), 60, ''),
                    'materials' => Str::limit((string) Arr::get($activity, 'materials', 'Sin materiales especiales'), 120, ''),
                    'homePractice' => Str::limit((string) Arr::get($activity, 'homePractice', ''), 260, ''),
                    'cautions' => Str::limit((string) Arr::get($activity, 'cautions', ''), 260, ''),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeStrings($items, int $limit, int $maxLength): array
    {
        if (is_string($items)) {
            $items = [$items];
        }

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn ($item) => filled($item))
            ->take($limit)
            ->map(fn ($item) => Str::limit((string) $item, $maxLength, ''))
            ->values()
            ->all();
    }
}
