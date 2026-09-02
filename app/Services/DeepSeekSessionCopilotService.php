<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class DeepSeekSessionCopilotService
{
    public function prepare(array $context, ?string $instructions = null): array
    {
        return $this->request('pre_session', $context, [
            'instructions' => $instructions,
        ]);
    }

    public function close(array $context, string $rawNotes, ?string $instructions = null): array
    {
        return $this->request('session_closure', $context, [
            'raw_notes' => $rawNotes,
            'instructions' => $instructions,
        ]);
    }

    private function request(string $mode, array $context, array $request): array
    {
        $apiKey = config('services.deepseek.api_key');
        if (! $apiKey) {
            throw new RuntimeException('DeepSeek no está configurado.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(config('services.deepseek.timeout', 35))
            ->post(rtrim(config('services.deepseek.base_url'), '/').'/chat/completions', [
                'model' => config('services.deepseek.model', 'deepseek-v4-flash'),
                'messages' => $this->messages($mode, $context, $request),
                'temperature' => 0.15,
                'max_tokens' => min((int) config('services.deepseek.max_tokens', 2600), 2600),
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            report(new RuntimeException('DeepSeek session copilot error: '.$response->body()));
            throw new RuntimeException('Adel no pudo preparar el borrador clínico.');
        }

        $content = data_get($response->json(), 'choices.0.message.content', '');
        if (is_array($content)) {
            $content = collect($content)->map(fn ($part) => data_get($part, 'text', ''))->filter()->implode("\n");
        }
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim((string) $content));
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Adel devolvió un borrador inválido.');
        }

        return [
            'output' => $mode === 'pre_session' ? $this->normalizePreparation($decoded) : $this->normalizeClosure($decoded),
            'model' => data_get($response->json(), 'model', config('services.deepseek.model')),
            'token_usage' => data_get($response->json(), 'usage'),
        ];
    }

    private function messages(string $mode, array $context, array $request): array
    {
        $schema = $mode === 'pre_session'
            ? 'overview:string, previous_session:string, pending_items:string[], recent_signals:string[], suggested_questions:string[], cautions:string[]'
            : 'objective:string, session_description:string, interventions:string, action_plan:string, observations:string';

        return [[
            'role' => 'system',
            'content' => implode("\n", [
                'Eres Adel, copiloto de documentación clínica para profesionales de psicología en MindMeet.',
                'Usa exclusivamente el contexto anonimizado y las notas proporcionadas.',
                'No diagnostiques, no inventes hechos, no determines riesgo y no des instrucciones de emergencia.',
                'Distingue claramente datos documentados de sugerencias para revisión profesional.',
                'No incluyas nombres, correos, teléfonos, direcciones ni identificadores.',
                $mode === 'pre_session'
                    ? 'Prepara una ficha breve para apoyar la siguiente sesión. Las preguntas y cautelas son sugerencias, no conclusiones.'
                    : 'Convierte las notas rápidas en un borrador clínico estructurado. Si un dato no está presente, deja el campo vacío.',
                "Responde únicamente JSON válido con: {$schema}.",
            ]),
        ], [
            'role' => 'user',
            'content' => json_encode([
                'modo' => $mode,
                'contexto_clinico_anonimizado' => $context,
                'solicitud_profesional' => $request,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]];
    }

    private function normalizePreparation(array $data): array
    {
        return [
            'overview' => $this->text(Arr::get($data, 'overview'), 1800),
            'previous_session' => $this->text(Arr::get($data, 'previous_session'), 1800),
            'pending_items' => $this->list(Arr::get($data, 'pending_items')),
            'recent_signals' => $this->list(Arr::get($data, 'recent_signals')),
            'suggested_questions' => $this->list(Arr::get($data, 'suggested_questions')),
            'cautions' => $this->list(Arr::get($data, 'cautions')),
        ];
    }

    private function normalizeClosure(array $data): array
    {
        return collect(['objective', 'session_description', 'interventions', 'action_plan', 'observations'])
            ->mapWithKeys(fn ($key) => [$key => $this->text(Arr::get($data, $key), 6000)])
            ->all();
    }

    private function list($value): array
    {
        return collect(is_array($value) ? $value : [])->map(fn ($item) => $this->text($item, 500))->filter()->take(8)->values()->all();
    }

    private function text($value, int $limit): string
    {
        if (! is_scalar($value)) return '';
        return Str::limit(trim(strip_tags((string) $value)), $limit, '');
    }
}
