<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ElenaAssistantService
{
    public function interpret(string $message, array $context = []): array
    {
        $apiKey = config('services.deepseek.api_key');

        if (! $apiKey) {
            return $this->fallbackInterpret($message);
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(config('services.deepseek.timeout', 35))
            ->post(rtrim(config('services.deepseek.base_url'), '/') . '/chat/completions', [
                'model' => config('services.deepseek.model', 'deepseek-v4-flash'),
                'messages' => $this->messages($message, $context),
                'temperature' => 0.1,
                'max_tokens' => 700,
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            report(new RuntimeException('DeepSeek Adel error: ' . $response->body()));
            return $this->fallbackInterpret($message);
        }

        $content = $this->normalizeContent(data_get($response->json(), 'choices.0.message.content'));
        $decoded = $this->decodeJsonContent($content);

        if (! is_array($decoded)) {
            Log::warning('DeepSeek Adel response was not valid JSON.', [
                'content_preview' => Str::limit($content, 600, ''),
                'usage' => data_get($response->json(), 'usage'),
            ]);

            return $this->fallbackInterpret($message);
        }

        return $this->normalizeIntent($decoded);
    }

    private function messages(string $message, array $context): array
    {
        return [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'Eres Adel, asistente operativo interno de MindMeet para psicologos.',
                    'Tu tarea es convertir mensajes en una intencion JSON para que MindMeet ejecute herramientas seguras.',
                    'No inventes pacientes, IDs ni datos clinicos. Extrae solo lo que el usuario dijo.',
                    'Para agendar, si el usuario dice un dia relativo como "martes", usa la siguiente ocurrencia futura segun fecha_actual.',
                    'Si la hora no especifica am/pm, infiere horario laboral razonable; 5 = 17:00 salvo que diga manana/madrugada.',
                    'Responde solo JSON valido con llaves:',
                    'intent: search_patient | next_session | schedule_session | help | unknown',
                    'patient_name: string|null',
                    'datetime_iso: string|null en zona horaria indicada, formato YYYY-MM-DDTHH:mm:ss',
                    'duration_minutes: integer|null, usa 50 si es sesion y no se especifica',
                    'format: online|presencial|mixto|null',
                    'session_type: string|null',
                    'price: number|null',
                    'confidence: number de 0 a 1',
                    'reply: string breve en espanol.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'mensaje' => $message,
                    'fecha_actual' => Arr::get($context, 'now'),
                    'timezone' => Arr::get($context, 'timezone'),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];
    }

    private function normalizeIntent(array $decoded): array
    {
        $intent = Arr::get($decoded, 'intent', 'unknown');
        if (! in_array($intent, ['search_patient', 'next_session', 'schedule_session', 'help', 'unknown'], true)) {
            $intent = 'unknown';
        }

        return [
            'intent' => $intent,
            'patient_name' => $this->nullableString(Arr::get($decoded, 'patient_name')),
            'datetime_iso' => $this->nullableString(Arr::get($decoded, 'datetime_iso')),
            'duration_minutes' => $this->duration(Arr::get($decoded, 'duration_minutes')),
            'format' => $this->normalizeFormat(Arr::get($decoded, 'format')),
            'session_type' => $this->nullableString(Arr::get($decoded, 'session_type')),
            'price' => is_numeric(Arr::get($decoded, 'price')) ? (float) Arr::get($decoded, 'price') : null,
            'confidence' => max(0, min(1, (float) Arr::get($decoded, 'confidence', 0))),
            'reply' => Str::limit((string) Arr::get($decoded, 'reply', ''), 260, ''),
        ];
    }

    private function fallbackInterpret(string $message): array
    {
        $normalized = Str::lower($message);
        $intent = 'unknown';

        if (Str::contains($normalized, ['siguiente sesion', 'proxima sesion', 'cuándo', 'cuando'])) {
            $intent = 'next_session';
        } elseif (Str::contains($normalized, ['agenda', 'agendar', 'crea una sesion', 'crear una sesion', 'programa'])) {
            $intent = 'schedule_session';
        } elseif (Str::contains($normalized, ['busca', 'buscar', 'encuentra'])) {
            $intent = 'search_patient';
        } elseif (Str::contains($normalized, ['ayuda', 'que puedes'])) {
            $intent = 'help';
        }

        return [
            'intent' => $intent,
            'patient_name' => $this->guessPatientName($message),
            'datetime_iso' => null,
            'duration_minutes' => $intent === 'schedule_session' ? 50 : null,
            'format' => null,
            'session_type' => null,
            'price' => null,
            'confidence' => $intent === 'unknown' ? 0 : 0.45,
            'reply' => '',
        ];
    }

    private function guessPatientName(string $message): ?string
    {
        if (preg_match('/(?:para|con|busca(?:r)?|encuentra)\s+([a-zA-ZÀ-ÿ\s]{3,80}?)(?:\s+el|\s+la|\s+este|\s+esta|\s+mañana|\s+manana|\s+martes|\s+miercoles|\s+miércoles|\s+jueves|\s+viernes|\s+sabado|\s+sábado|\s+domingo|\s+lunes|$)/iu', $message, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function normalizeFormat($value): ?string
    {
        $value = Str::lower(trim((string) $value));
        return in_array($value, ['online', 'presencial', 'mixto'], true) ? $value : null;
    }

    private function duration($value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(15, min(240, (int) $value));
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' || Str::lower($value) === 'null' ? null : $value;
    }

    private function normalizeContent($content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (is_array($content)) {
            return collect($content)
                ->map(fn ($part) => is_string($part) ? $part : (data_get($part, 'text') ?: data_get($part, 'content') ?: ''))
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
}
