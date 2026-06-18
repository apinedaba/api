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
                    'is_recurrent: boolean|null',
                    'recurrence_frequency: DAILY|WEEKLY|MONTHLY|null',
                    'recurrence_until: string|null, formato YYYY-MM-DD',
                    'recurrence_interval: integer|null, usa 1 si no se especifica',
                    'payment_status: paid|pending|null',
                    'price: number|null',
                    'confidence: number de 0 a 1',
                    'reply: string breve en espanol.',
                    'Si recibes previous_intent, completa los campos faltantes con el mensaje nuevo y conserva la intencion original.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'mensaje' => $message,
                    'fecha_actual' => Arr::get($context, 'now'),
                    'timezone' => Arr::get($context, 'timezone'),
                    'previous_intent' => Arr::get($context, 'previous_intent'),
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
            'is_recurrent' => $this->nullableBoolean(Arr::get($decoded, 'is_recurrent')),
            'recurrence_frequency' => $this->normalizeFrequency(Arr::get($decoded, 'recurrence_frequency')),
            'recurrence_until' => $this->nullableString(Arr::get($decoded, 'recurrence_until')),
            'recurrence_interval' => is_numeric(Arr::get($decoded, 'recurrence_interval')) ? max(1, (int) Arr::get($decoded, 'recurrence_interval')) : null,
            'payment_status' => $this->normalizePaymentStatus(Arr::get($decoded, 'payment_status')),
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
            'format' => $this->guessFormat($normalized),
            'session_type' => $this->guessSessionType($message),
            'is_recurrent' => $this->guessRecurrence($normalized),
            'recurrence_frequency' => $this->guessFrequency($normalized),
            'recurrence_until' => null,
            'recurrence_interval' => 1,
            'payment_status' => $this->guessPaymentStatus($normalized),
            'price' => $this->guessPrice($message),
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

    private function normalizeFrequency($value): ?string
    {
        $value = Str::upper(trim((string) $value));
        return in_array($value, ['DAILY', 'WEEKLY', 'MONTHLY'], true) ? $value : null;
    }

    private function normalizePaymentStatus($value): ?string
    {
        $value = Str::lower(trim((string) $value));
        return in_array($value, ['paid', 'pending'], true) ? $value : null;
    }

    private function nullableBoolean($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        $value = Str::lower(trim((string) $value));
        if (in_array($value, ['true', '1', 'si', 'sí', 'yes'], true)) {
            return true;
        }
        if (in_array($value, ['false', '0', 'no'], true)) {
            return false;
        }

        return null;
    }

    private function guessFormat(string $normalized): ?string
    {
        if (Str::contains($normalized, ['presencial', 'consultorio'])) {
            return 'presencial';
        }
        if (Str::contains($normalized, ['mixto'])) {
            return 'mixto';
        }
        if (Str::contains($normalized, ['online', 'virtual', 'videollamada', 'zoom'])) {
            return 'online';
        }

        return null;
    }

    private function guessSessionType(string $message): ?string
    {
        if (preg_match('/(?:tipo(?: de)? sesion|sesion)\s+(individual|pareja|familiar|infantil|adolescente|primera vez|seguimiento)/iu', $message, $matches)) {
            return trim($matches[1]);
        }
        if (preg_match('/\b(individual|pareja|familiar|infantil|adolescente|primera vez|seguimiento)\b/iu', $message, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function guessRecurrence(string $normalized): ?bool
    {
        if (Str::contains($normalized, ['no recurrente', 'sin recurrencia', 'solo una vez', 'unica', 'única'])) {
            return false;
        }
        if (Str::contains($normalized, ['recurrente', 'cada semana', 'semanal', 'mensual', 'diaria', 'diario'])) {
            return true;
        }

        return null;
    }

    private function guessFrequency(string $normalized): ?string
    {
        if (Str::contains($normalized, ['diaria', 'diario', 'cada dia', 'cada día'])) {
            return 'DAILY';
        }
        if (Str::contains($normalized, ['mensual', 'cada mes'])) {
            return 'MONTHLY';
        }
        if (Str::contains($normalized, ['semanal', 'cada semana'])) {
            return 'WEEKLY';
        }

        return null;
    }

    private function guessPaymentStatus(string $normalized): ?string
    {
        if (Str::contains($normalized, ['ya pago', 'ya pagó', 'pagado', 'pago completo', 'pagó'])) {
            return 'paid';
        }
        if (Str::contains($normalized, ['pendiente', 'no pago', 'no pagó', 'por pagar'])) {
            return 'pending';
        }

        return null;
    }

    private function guessPrice(string $message): ?float
    {
        if (preg_match('/(?:\$|mxn\s*)\s*([0-9]+(?:[.,][0-9]+)?)/iu', $message, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }
        if (preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*(?:pesos|mxn)/iu', $message, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }
        if (preg_match('/gratis|sin costo|costo cero/iu', $message)) {
            return 0.0;
        }

        return null;
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
