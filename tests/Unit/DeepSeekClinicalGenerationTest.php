<?php

namespace Tests\Unit;

use App\Services\DeepSeekExerciseService;
use App\Services\DeepSeekPatientSummaryService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DeepSeekClinicalGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.deepseek.api_key', 'test-key');
        config()->set('services.deepseek.base_url', 'https://deepseek.test');
        config()->set('services.deepseek.model', 'deepseek-chat');
        config()->set('services.deepseek.max_tokens', 2600);
    }

    public function test_summary_uses_ai_and_requires_a_substantive_result(): void
    {
        $content = implode("\n", [
            'Motivo y contexto',
            'Se documenta ansiedad asociada con interacciones laborales y evitacion de reuniones.',
            'Sintesis del proceso',
            'Las notas muestran practica gradual y mayor identificacion de pensamientos anticipatorios.',
            'Evolucion y respuesta',
            'Existe avance documentado en participacion, aunque persiste evitacion en grupos grandes.',
            'Consideraciones actuales',
            'Conviene revisar frecuencia, intensidad y efecto funcional con informacion actualizada.',
            'Siguientes pasos o preguntas pendientes',
            'Acordar un indicador observable y revisar barreras antes de aumentar la dificultad.',
        ]);

        Http::fake([
            'https://deepseek.test/chat/completions' => Http::response([
                'model' => 'deepseek-chat',
                'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 180],
                'choices' => [['message' => ['content' => json_encode([
                    'title' => 'Resumen de evolución',
                    'content' => $content,
                ])]]],
            ]),
        ]);

        $result = (new DeepSeekPatientSummaryService())->generate(
            ['admision' => ['motivo_consulta' => 'Ansiedad laboral']],
            ['recipient' => 'psychiatrist', 'detail' => 'detailed', 'sections' => ['intake', 'sessions']]
        );

        $this->assertSame('deepseek-chat', $result['model']);
        $this->assertSame($content, $result['content']);
        $this->assertNotNull($result['token_usage']);

        Http::assertSent(fn ($request) =>
            str_contains(data_get($request->data(), 'messages.0.content', ''), 'No te limites a copiar')
            && data_get($request->data(), 'response_format.type') === 'json_object'
        );
    }

    public function test_exercise_generation_requires_actionable_clinical_content(): void
    {
        Http::fake([
            'https://deepseek.test/chat/completions' => Http::response([
                'model' => 'deepseek-chat',
                'usage' => ['prompt_tokens' => 90, 'completion_tokens' => 150],
                'choices' => [['message' => ['content' => json_encode([
                    'summary' => 'Propuesta graduada para trabajar evitacion.',
                    'activities' => [[
                        'title' => 'Mapa de aproximacion gradual',
                        'objective' => 'Reducir evitacion en reuniones.',
                        'clinicalRationale' => 'La exposicion graduada permite observar predicciones y tolerar malestar en condiciones controladas.',
                        'steps' => ['Definir situacion', 'Ordenar dificultad', 'Practicar un nivel'],
                        'duration' => '20 minutos',
                        'materials' => 'Hoja y lapiz',
                        'homePractice' => 'Registrar una practica.',
                        'successIndicator' => 'Participa cinco minutos y registra ansiedad antes y despues.',
                        'adaptations' => ['Reducir duracion si aumenta demasiado el malestar'],
                        'cautions' => 'Ajustar ritmo con el paciente.',
                    ]],
                    'quickIdeas' => [],
                    'safetyNotes' => [],
                ])]]],
            ]),
        ]);

        $result = (new DeepSeekExerciseService())->generate(
            ['expediente' => ['diagnostico_registrado_por_psicologo' => 'Ansiedad']],
            ['mode' => 'activities', 'quantity' => 1, 'focus' => 'Evitacion laboral']
        );

        $this->assertSame('ai', $result['generatedBy']);
        $this->assertSame('Mapa de aproximacion gradual', $result['activities'][0]['title']);
        $this->assertNotEmpty($result['activities'][0]['clinicalRationale']);
        $this->assertNotEmpty($result['activities'][0]['successIndicator']);
    }

    public function test_exercise_generation_rejects_empty_or_generic_payloads(): void
    {
        Http::fake([
            'https://deepseek.test/chat/completions' => Http::response([
                'model' => 'deepseek-chat',
                'choices' => [['message' => ['content' => json_encode([
                    'summary' => 'Informacion fusionada.',
                    'activities' => [],
                    'quickIdeas' => [],
                    'safetyNotes' => [],
                ])]]],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no genero actividades clinicas suficientemente completas');

        (new DeepSeekExerciseService())->generate([], ['mode' => 'activities']);
    }
}
