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

        if (! is_array($decoded)) {
            throw new RuntimeException('Adel devolvio un resumen invalido.');
        }

        $summaryContent = trim((string) Arr::get($decoded, 'content'));

        if (mb_strlen($summaryContent) < 180) {
            throw new RuntimeException('Adel devolvio un resumen invalido.');
        }

        return [
            'title' => Str::limit((string) Arr::get($decoded, 'title', 'Resumen clinico'), 180, ''),
            'content' => $summaryContent,
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
                    'Redacta un informe clínico narrativo, listo para compartir y revisar por el profesional tratante, usando exclusivamente el JSON anonimo proporcionado.',
                    'No te limites a copiar, enumerar, parafrasear por separado ni fusionar campos. Convierte los datos en una narrativa profesional: explica qué se trabajó, qué se observó, cómo respondió la persona y por qué se proponen los siguientes pasos.',
                    'El resultado debe parecer un informe escrito por un profesional, con párrafos completos y conectores clínicos naturales; no un resumen automático de expediente.',
                    'Identifica patrones longitudinales, cambios, recursos, dificultades, respuesta a intervenciones y asuntos pendientes solo cuando exista evidencia suficiente.',
                    'Integra el historial como un proceso continuo. Nunca redactes una bitácora sesión por sesión, no menciones cada fecha ni uses fórmulas como "en la sesión X". Describe solo los cambios relevantes entre el inicio, el proceso y el momento actual.',
                    'Integra objetivos, intervenciones y resultados documentados como parte de la evolución global. Si existen escalas, cuestionarios, métricas o registros del diario, explica su tendencia e implicación clínica en prosa; no presentes puntajes, respuestas ni métricas como una tabla, inventario o lista.',
                    'Incluye recomendaciones concretas y prudentes solo cuando se deriven de lo documentado. Si hay contradicciones o vacios relevantes, señalalos como informacion pendiente, no los completes.',
                    'No inventes hechos, diagnosticos, fechas ni conclusiones. Distingue datos documentados de inferencias clinicas prudentes.',
                    'Adapta lenguaje, profundidad y tecnicismos al destinatario indicado.',
                    'Para familia o escuela evita detalles sensibles innecesarios y lenguaje estigmatizante.',
                    'Para psiquiatria prioriza motivo, evolucion, sintomas, interpretación narrativa de escalas cuando sean pertinentes, intervenciones, medicacion y preguntas de interconsulta si estan presentes.',
                    'No incluyas nombre, correo, telefono, direccion, IDs ni otros identificadores.',
                    'Incluye al final: Documento generado con apoyo de IA y sujeto a revision del profesional tratante.',
                    'Usa esta estructura cuando exista evidencia: "A quien corresponda:"; "Motivo y contexto"; "Síntesis del proceso"; "Evolución y respuesta"; "Consideraciones actuales"; "Recomendaciones y siguientes pasos". Cada sección debe contener uno o más párrafos redactados, no viñetas de datos.',
                    'Para un informe breve redacta aproximadamente 350 a 600 palabras. Para uno detallado, 700 a 1200 palabras. Omite encabezados sin evidencia y evita repetir el mismo dato en secciones distintas.',
                    'Responde solo JSON valido con title y content. content debe ser texto plano con encabezados en una línea independiente y saltos de línea entre párrafos. No uses Markdown, JSON dentro del contenido, ni etiquetas HTML.',
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
