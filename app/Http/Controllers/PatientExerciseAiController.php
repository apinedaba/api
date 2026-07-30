<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AiExerciseGeneration;
use App\Models\EmotionLog;
use App\Models\Expediente;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\QuestionnaireLink;
use App\Services\DeepSeekExerciseService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PatientExerciseAiController extends Controller
{
    private const MONTHLY_LIMIT = 20;

    public function __construct(private DeepSeekExerciseService $deepSeek)
    {
    }

    public function index(Request $request, Patient $patient)
    {
        $psychologist = $request->user();

        PatientUser::where('patient', $patient->id)
            ->where('user', $psychologist->id)
            ->firstOrFail();

        $history = AiExerciseGeneration::where('patient_id', $patient->id)
            ->where('user_id', $psychologist->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (AiExerciseGeneration $generation) => $this->serializeGeneration($generation))
            ->values();

        return response()->json([
            'data' => $history,
            'quota' => $this->quotaFor($psychologist->id),
        ]);
    }

    public function generate(Request $request, Patient $patient)
    {
        $psychologist = $request->user();
        $relationship = PatientUser::where('patient', $patient->id)
            ->where('user', $psychologist->id)
            ->firstOrFail();

        if ($relationship->archived_at) {
            return response()->json([
                'message' => 'Paciente archivado. Reactivalo para generar actividades.',
            ], 423);
        }

        $validated = $request->validate([
            'mode' => 'nullable|string|in:activities,quick_ideas,adapt,homework,session_plan,risk_sensitive',
            'focus' => 'nullable|string|max:500',
            'format' => 'nullable|string|max:80',
            'duration' => 'nullable|string|max:40',
            'quantity' => 'nullable|integer|min:1|max:5',
            'context' => 'nullable|array',
            'context.mainGoal' => 'nullable|string|max:500',
            'context.constraints' => 'nullable|string|max:500',
            'context.preferredApproach' => 'nullable|string|max:180',
        ]);

        $quota = $this->quotaFor($psychologist->id);

        if ($quota['remaining'] <= 0) {
            return response()->json([
                'message' => 'Alcanzaste el limite mensual de solicitudes de IA.',
                'quota' => $quota,
            ], 429);
        }

        try {
            $result = $this->deepSeek->generate(
                $this->buildClinicalContext($patient, $psychologist->id, Arr::get($validated, 'context', [])),
                $validated
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage() ?: 'No se pudo generar la sugerencia con IA.',
            ], 502);
        }

        $generation = AiExerciseGeneration::create([
            'organization_id' => $patient->organization_id,
            'user_id' => $psychologist->id,
            'patient_id' => $patient->id,
            'mode' => Arr::get($validated, 'mode', 'activities'),
            'model' => Arr::get($result, 'model'),
            'request_payload' => $this->storedRequestPayload($validated),
            'response_payload' => Arr::except($result, ['tokenUsage']),
            'token_usage' => Arr::get($result, 'tokenUsage'),
        ]);

        return response()->json([
            'data' => $result,
            'generation' => $this->serializeGeneration($generation),
            'quota' => $this->quotaFor($psychologist->id),
            'privacy' => [
                'Paciente desidentificado antes de enviar a DeepSeek.',
                'No se enviaron nombre, correo, telefono, direccion, archivos ni identificadores directos.',
                'Las actividades son sugerencias para revision profesional, no instrucciones clinicas automaticas.',
            ],
        ]);
    }

    private function quotaFor(int $psychologistId): array
    {
        $now = now();
        $used = AiExerciseGeneration::where('user_id', $psychologistId)
            ->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->count();

        return [
            'limit' => self::MONTHLY_LIMIT,
            'used' => $used,
            'remaining' => max(0, self::MONTHLY_LIMIT - $used),
            'period' => $now->format('Y-m'),
            'resets_at' => $now->copy()->addMonthNoOverflow()->startOfMonth()->toDateString(),
        ];
    }

    private function serializeGeneration(AiExerciseGeneration $generation): array
    {
        return [
            'id' => $generation->id,
            'mode' => $generation->mode,
            'model' => $generation->model,
            'request' => $generation->request_payload,
            'response' => $generation->response_payload,
            'tokenUsage' => $generation->token_usage,
            'created_at' => optional($generation->created_at)->toISOString(),
        ];
    }

    private function storedRequestPayload(array $validated): array
    {
        return [
            'mode' => Arr::get($validated, 'mode', 'activities'),
            'focus' => $this->clean(Arr::get($validated, 'focus')),
            'format' => $this->clean(Arr::get($validated, 'format')),
            'duration' => $this->clean(Arr::get($validated, 'duration')),
            'quantity' => Arr::get($validated, 'quantity', 3),
            'context' => [
                'mainGoal' => $this->clean(Arr::get($validated, 'context.mainGoal')),
                'constraints' => $this->clean(Arr::get($validated, 'context.constraints')),
                'preferredApproach' => $this->clean(Arr::get($validated, 'context.preferredApproach')),
            ],
        ];
    }

    private function buildClinicalContext(Patient $patient, int $psychologistId, array $extraContext): array
    {
        $expediente = Expediente::where('patient_id', $patient->id)
            ->where('user_id', $psychologistId)
            ->first();

        $sessions = Appointment::where('patient', $patient->id)
            ->where('user', $psychologistId)
            ->orderByDesc('start')
            ->limit(4)
            ->get();

        $questionnaires = QuestionnaireLink::where('patient', $patient->id)
            ->where('user', $psychologistId)
            ->with(['questionnaire:id,title', 'questionnaireLink'])
            ->latest()
            ->limit(4)
            ->get();

        $emotionLogs = EmotionLog::where('patient_id', $patient->id)
            ->latest()
            ->limit(5)
            ->get();

        return $this->compactContext([
            'paciente' => [
                'edad' => $this->ageFromDate(data_get($patient->relevantes, 'fechaNac')),
                'genero' => data_get($patient->relevantes, 'genero') ?: data_get($patient->relevantes, 'sexo'),
                'ocupacion' => data_get($patient->relevantes, 'ocupacion'),
                'estado_civil' => data_get($patient->relevantes, 'estadoCivil'),
            ],
            'admision' => [
                'motivo_consulta' => $this->clean(data_get($patient->historiaClinica, 'clinical_intake.motivo_consulta') ?: $expediente?->motivoConsulta ?: data_get($patient->historiaClinica, 'motivoConsulta')),
                'medicamentos' => $this->clean(data_get($patient->historiaClinica, 'clinical_intake.medicamentos')),
                'terapia_previa' => [
                    'psicologica' => data_get($patient->historiaClinica, 'clinical_intake.terapia_psicologica_previa'),
                    'detalle_psicologica' => $this->clean(data_get($patient->historiaClinica, 'clinical_intake.terapia_psicologica_detalle')),
                    'psiquiatrica' => data_get($patient->historiaClinica, 'clinical_intake.terapia_psiquiatrica_previa'),
                    'detalle_psiquiatrica' => $this->clean(data_get($patient->historiaClinica, 'clinical_intake.terapia_psiquiatrica_detalle')),
                ],
            ],
            'expediente' => [
                'diagnostico_registrado_por_psicologo' => $this->clean($expediente?->diagnostico),
                'plan_tratamiento' => $this->clean($expediente?->plan_tratamiento),
                'escalas' => $this->scaleSummary($expediente?->escalas ?? []),
                'examen_mental_resumen' => $this->filledKeys($expediente?->examen_mental ?? []),
                'antecedentes_resumen' => $this->clean($expediente?->antecedentes),
            ],
            'sesiones_recientes' => $sessions->map(fn ($session) => [
                'fecha_relativa' => optional($session->start)->diffForHumans(),
                'objetivo' => $this->clean($session->objective),
                'descripcion' => $this->clean($session->session_description ?: $session->comments),
                'intervenciones' => $this->clean($session->interventions),
                'plan_accion' => $this->clean($session->action_plan),
                'observaciones' => $this->clean($session->observations),
            ])->all(),
            'cuestionarios_recientes' => $questionnaires->map(fn ($link) => [
                'titulo' => $this->clean($link->questionnaire?->title),
                'estado' => $link->questionnaireLink?->status ?? 'sin_respuesta',
                'resumen_respuesta' => $this->clean($link->questionnaireLink?->response),
            ])->all(),
            'diario_emocional_reciente' => $emotionLogs->map(fn ($log) => [
                'emocion' => $this->clean($log->emotion ?: $log->feeling),
                'intensidad' => $log->intensity,
                'situacion' => $this->clean($log->situation),
                'conducta' => $this->clean($log->behavior),
                'respuesta_adaptativa' => $this->clean($log->adaptive_response),
            ])->all(),
            'solicitud_psicologo' => [
                'objetivo_principal' => $this->clean(Arr::get($extraContext, 'mainGoal')),
                'restricciones' => $this->clean(Arr::get($extraContext, 'constraints')),
                'enfoque_preferido' => $this->clean(Arr::get($extraContext, 'preferredApproach')),
            ],
        ]);
    }

    private function compactContext(array $context): array
    {
        return collect($context)
            ->map(fn ($value) => is_array($value) ? $this->compactNested($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])
            ->all();
    }

    private function compactNested(array $items): array
    {
        return collect($items)
            ->map(fn ($value) => is_array($value) ? $this->compactNested($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])
            ->all();
    }

    private function clean($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)));

        return $text ? Str::limit($text, 700, '') : null;
    }

    private function ageFromDate($value): ?int
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    private function filledKeys(array $items): array
    {
        return collect($items)
            ->filter(fn ($value) => filled($value))
            ->keys()
            ->take(12)
            ->values()
            ->all();
    }

    private function scaleSummary(array $scales): array
    {
        return collect($scales)
            ->take(6)
            ->map(function ($scale) {
                $items = Arr::get($scale, 'items', []);
                $total = is_array($items)
                    ? collect($items)->sum(fn ($item) => (int) Arr::get($item, 'value', 0))
                    : null;

                return [
                    'escala' => Arr::get($scale, 'label', 'Escala'),
                    'total' => $total,
                    'referencia' => Arr::get($scale, 'reference'),
                ];
            })
            ->all();
    }
}
