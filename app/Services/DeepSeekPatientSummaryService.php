<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class DeepSeekPatientSummaryService
{
    public function generate(array $clinicalContext, array $options): array
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
                'messages' => $this->messages($clinicalContext, $options),
                'temperature' => 0.2,
                'max_tokens' => min((int) config('services.deepseek.max_tokens', 2600), 2600),
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('No se pudo generar el resumen con Adel.');
        }

        $content = data_get($response->json(), 'choices.0.message.content', '');
        if (is_array($content)) {
            $content = collect($content)->pluck('text')->filter()->implode("\n");
        }
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim((string) $content));
        $decoded = json_decode($content, true);

        if (! is_array($decoded) || ! filled(Arr::get($decoded, 'content'))) {
            throw new RuntimeException('Adel devolvio un resumen invalido.');
        }

        return [
            'title' => Str::limit((string) Arr::get($decoded, 'title', 'Resumen clinico'), 180, ''),
            'content' => trim((string) Arr::get($decoded, 'content')),
            'model' => data_get($response->json(), 'model', config('services.deepseek.model')),
            'token_usage' => data_get($response->json(), 'usage'),
        ];
    }

    private function messages(array $clinicalContext, array $options): array
    {
        return [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'Eres Adel, asistente de documentacion clinica de MindMeet.',
                    'Redacta un resumen profesional usando exclusivamente el JSON anonimo proporcionado.',
                    'No inventes hechos, diagnosticos, fechas ni conclusiones. Distingue datos documentados de observaciones.',
                    'Adapta lenguaje, profundidad y tecnicismos al destinatario indicado.',
                    'Para familia o escuela evita detalles sensibles innecesarios y lenguaje estigmatizante.',
                    'Para psiquiatria prioriza motivo, evolucion, sintomas, escalas, intervenciones, medicacion y preguntas de interconsulta si estan presentes.',
                    'No incluyas nombre, correo, telefono, direccion, IDs ni otros identificadores.',
                    'Incluye al final: Documento generado con apoyo de IA y sujeto a revision del profesional tratante.',
                    'Responde solo JSON valido con title y content. content debe ser texto claro con encabezados y saltos de linea, sin Markdown complejo.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'destinatario' => Arr::get($options, 'recipient'),
                    'nivel_detalle' => Arr::get($options, 'detail', 'brief'),
                    'instrucciones_profesional' => Arr::get($options, 'instructions'),
                    'secciones_autorizadas' => Arr::get($options, 'sections', []),
                    'expediente_anonimizado' => $clinicalContext,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];
    }
}
