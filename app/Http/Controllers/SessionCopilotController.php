<?php

namespace App\Http\Controllers;

use App\Models\AiSessionCopilotDraft;
use App\Models\Appointment;
use App\Models\EmotionLog;
use App\Models\Expediente;
use App\Models\PatientUser;
use App\Models\QuestionnaireLink;
use App\Services\DeepSeekSessionCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SessionCopilotController extends Controller
{
    private const CLINICAL_FIELDS = ['objective', 'session_description', 'interventions', 'action_plan', 'observations'];

    public function __construct(private DeepSeekSessionCopilotService $copilot)
    {
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);

        return response()->json(['data' => AiSessionCopilotDraft::query()
            ->where('appointment_id', $appointment->id)
            ->where('user_id', $request->user()->id)
            ->latest()->limit(10)->get()->map(fn ($draft) => $this->serialize($draft))]);
    }

    public function prepare(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);
        $validated = $request->validate(['instructions' => ['nullable', 'string', 'max:800']]);

        try {
            $result = $this->copilot->prepare($this->clinicalContext($appointment), $validated['instructions'] ?? null);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => $exception->getMessage() ?: 'No se pudo preparar la sesión.'], 502);
        }

        return $this->storeDraft($request, $appointment, 'pre_session', $validated, $result);
    }

    public function close(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);
        $validated = $request->validate([
            'raw_notes' => ['required', 'string', 'min:10', 'max:10000'],
            'instructions' => ['nullable', 'string', 'max:800'],
        ]);

        try {
            $result = $this->copilot->close(
                $this->clinicalContext($appointment),
                $validated['raw_notes'],
                $validated['instructions'] ?? null
            );
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => $exception->getMessage() ?: 'No se pudo preparar el cierre.'], 502);
        }

        return $this->storeDraft($request, $appointment, 'session_closure', $validated, $result);
    }

    public function apply(Request $request, Appointment $appointment, AiSessionCopilotDraft $draft): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);
        abort_unless($draft->appointment_id === $appointment->id && $draft->user_id === $request->user()->id, 404);
        abort_unless($draft->mode === 'session_closure', 422, 'Solo los borradores de cierre se pueden aplicar.');
        abort_unless($draft->status === 'draft', 409, 'Este borrador ya fue aplicado. Genera uno nuevo para hacer más cambios.');

        $rules = collect(self::CLINICAL_FIELDS)->mapWithKeys(fn ($field) => [$field => ['nullable', 'string', 'max:10000']])->all();
        $validated = $request->validate($rules);
        $fields = Arr::only($validated, self::CLINICAL_FIELDS);

        $appointment->update($fields);
        $draft->update([
            'status' => 'applied',
            'output_payload' => $fields,
            'applied_at' => now(),
        ]);

        return response()->json([
            'message' => 'Borrador revisado y guardado en la sesión.',
            'data' => $this->serialize($draft->fresh()),
            'appointment' => $appointment->fresh(),
        ]);
    }

    private function storeDraft(Request $request, Appointment $appointment, string $mode, array $input, array $result): JsonResponse
    {
        $draft = AiSessionCopilotDraft::create([
            'appointment_id' => $appointment->id,
            'user_id' => $request->user()->id,
            'patient_id' => $this->patientId($appointment),
            'mode' => $mode,
            'input_payload' => $input,
            'output_payload' => $result['output'],
            'model' => $result['model'],
            'token_usage' => $result['token_usage'],
        ]);

        return response()->json([
            'data' => $this->serialize($draft),
            'privacy' => 'El contexto fue desidentificado y el resultado requiere revisión profesional.',
        ], 201);
    }

    private function authorizeAppointment(Request $request, Appointment $appointment): void
    {
        abort_unless($this->userId($appointment) === (int) $request->user()->id, 403);
        PatientUser::where('patient', $this->patientId($appointment))->where('user', $request->user()->id)->firstOrFail();
    }

    private function clinicalContext(Appointment $appointment): array
    {
        $patient = $appointment->patient()->firstOrFail();
        $userId = $this->userId($appointment);
        $expediente = Expediente::where('patient_id', $patient->id)->where('user_id', $userId)->first();
        $previousSessions = Appointment::where('patient', $patient->id)->where('user', $userId)
            ->where('id', '!=', $appointment->id)->where('start', '<', $appointment->start)
            ->orderByDesc('start')->limit(5)->get();

        return $this->compact([
            'sesion_actual' => [
                'fecha' => optional($appointment->start)->toDateTimeString(),
                'tipo' => data_get($appointment->extendedProps, 'tipoSesion'),
                'formato' => $appointment->sessionFormat(),
                'nota_previa' => $this->clean($appointment->pre_session_note),
            ],
            'perfil' => [
                'edad' => $this->age(data_get($patient->relevantes, 'fechaNac')),
                'genero' => data_get($patient->relevantes, 'genero') ?: data_get($patient->relevantes, 'sexo'),
                'ocupacion' => data_get($patient->relevantes, 'ocupacion'),
            ],
            'expediente' => [
                'motivo_consulta' => $this->clean($expediente?->motivoConsulta),
                'diagnostico_documentado' => $this->clean($expediente?->diagnostico),
                'plan_tratamiento' => $this->clean($expediente?->plan_tratamiento),
                'medicacion_reportada' => $this->clean(data_get($patient->historiaClinica, 'clinical_intake.medicamentos')),
            ],
            'sesiones_previas' => $previousSessions->map(fn ($session) => $this->compact([
                'fecha' => optional($session->start)->toDateString(),
                'objetivo' => $this->clean($session->objective),
                'descripcion' => $this->clean($session->session_description ?: $session->comments),
                'intervenciones' => $this->clean($session->interventions),
                'plan_accion' => $this->clean($session->action_plan),
                'observaciones' => $this->clean($session->observations),
            ]))->all(),
            'cuestionarios_recientes' => QuestionnaireLink::where('patient', $patient->id)->where('user', $userId)
                ->with(['questionnaire:id,title', 'questionnaireLink'])->latest()->limit(4)->get()->map(fn ($link) => $this->compact([
                    'titulo' => $link->questionnaire?->title,
                    'estado' => $link->questionnaireLink?->status,
                    'respuesta' => $this->clean($link->questionnaireLink?->response),
                ]))->all(),
            'diario_emocional_reciente' => EmotionLog::where('patient_id', $patient->id)->latest()->limit(6)->get()->map(fn ($log) => $this->compact([
                'emocion' => $log->emotion ?: $log->feeling,
                'intensidad' => $log->intensity,
                'situacion' => $this->clean($log->situation),
                'respuesta_adaptativa' => $this->clean($log->adaptive_response),
            ]))->all(),
        ]);
    }

    private function clean($value): ?string
    {
        if (is_array($value) || is_object($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_scalar($value) || ! filled($value)) return null;
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $value))), 1600, '');
    }

    private function compact(array $items): array
    {
        return collect($items)->map(fn ($value) => is_array($value) ? $this->compact($value) : $value)
            ->filter(fn ($value) => ! in_array($value, [null, '', []], true))->all();
    }

    private function age($date): ?int
    {
        try { return $date ? now()->diffInYears($date) : null; } catch (\Throwable) { return null; }
    }

    private function serialize(AiSessionCopilotDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'mode' => $draft->mode,
            'status' => $draft->status,
            'output' => $draft->output_payload,
            'created_at' => optional($draft->created_at)->toISOString(),
            'applied_at' => optional($draft->applied_at)->toISOString(),
        ];
    }

    private function userId(Appointment $appointment): int
    {
        return (int) $appointment->getRawOriginal('user');
    }

    private function patientId(Appointment $appointment): int
    {
        return (int) $appointment->getRawOriginal('patient');
    }
}
