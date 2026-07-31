<?php

namespace App\Http\Controllers;

use App\Models\AiPatientSummary;
use App\Models\Appointment;
use App\Models\EmotionLog;
use App\Models\Expediente;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\QuestionnaireLink;
use App\Services\DeepSeekPatientSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PatientSummaryAiController extends Controller
{
    private const ALLOWED_SECTIONS = [
        'profile', 'intake', 'diagnosis', 'treatment_plan', 'medications',
        'scales', 'mental_exam', 'sessions', 'questionnaires', 'emotion_diary',
    ];

    public function __construct(private DeepSeekPatientSummaryService $deepSeek)
    {
    }

    public function index(Request $request, Patient $patient)
    {
        $this->relationship($request, $patient);

        return response()->json(['data' => AiPatientSummary::query()
            ->where('patient_id', $patient->id)
            ->where('user_id', $request->user()->id)
            ->latest()->limit(20)->get()->map(fn ($summary) => $this->serialize($summary))]);
    }

    public function generate(Request $request, Patient $patient)
    {
        $relationship = $this->relationship($request, $patient);
        abort_if($relationship->archived_at, 423, 'Reactiva al paciente para generar un resumen.');

        $validated = $request->validate([
            'recipient' => 'required|string|in:psychiatrist,family,school,patient,other',
            'detail' => 'nullable|string|in:brief,detailed',
            'sections' => 'required|array|min:1',
            'sections.*' => 'required|string|in:' . implode(',', self::ALLOWED_SECTIONS),
            'instructions' => 'nullable|string|max:1000',
        ]);
        $sections = array_values(array_unique($validated['sections']));

        try {
            $result = $this->deepSeek->generate(
                $this->clinicalContext($patient, $request->user()->id, $sections),
                [...$validated, 'sections' => $sections]
            );
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => $exception->getMessage() ?: 'No se pudo generar el resumen.'], 502);
        }

        $summary = AiPatientSummary::create([
            'organization_id' => $patient->organization_id,
            'user_id' => $request->user()->id,
            'patient_id' => $patient->id,
            'recipient' => $validated['recipient'],
            'title' => Str::limit($result['title'] . ' - ' . $patient->name, 180, ''),
            'content' => $result['content'],
            'included_sections' => $sections,
            'instructions' => $validated['instructions'] ?? null,
            'model' => $result['model'],
            'token_usage' => $result['token_usage'],
        ]);

        return response()->json([
            'data' => $this->serialize($summary),
            'privacy' => 'Los datos se desidentificaron antes de enviarse a Adel.',
        ], 201);
    }

    public function update(Request $request, Patient $patient, AiPatientSummary $summary)
    {
        $this->relationship($request, $patient);
        abort_unless($summary->patient_id === $patient->id && $summary->user_id === $request->user()->id, 404);
        $validated = $request->validate([
            'title' => 'required|string|max:180',
            'content' => 'required|string|max:30000',
        ]);
        $summary->update($validated);

        return response()->json(['data' => $this->serialize($summary->fresh())]);
    }

    private function relationship(Request $request, Patient $patient): PatientUser
    {
        return PatientUser::where('patient', $patient->id)->where('user', $request->user()->id)->firstOrFail();
    }

    private function clinicalContext(Patient $patient, int $userId, array $sections): array
    {
        $expediente = Expediente::where('patient_id', $patient->id)->where('user_id', $userId)->first();
        $context = [];
        $has = fn (string $section) => in_array($section, $sections, true);

        if ($has('profile')) $context['perfil'] = $this->compact([
            'edad' => $this->age(data_get($patient->relevantes, 'fechaNac')),
            'genero' => data_get($patient->relevantes, 'genero') ?: data_get($patient->relevantes, 'sexo'),
            'ocupacion' => data_get($patient->relevantes, 'ocupacion'),
            'estado_civil' => data_get($patient->relevantes, 'estadoCivil'),
        ]);
        if ($has('intake')) $context['admision'] = $this->compact([
            'motivo_consulta' => $expediente?->motivoConsulta ?: data_get($patient->historiaClinica, 'clinical_intake.motivo_consulta'),
            'antecedentes' => $expediente?->antecedentes,
            'terapia_previa' => data_get($patient->historiaClinica, 'clinical_intake.terapia_psicologica_detalle'),
        ]);
        if ($has('diagnosis')) $context['diagnostico_documentado'] = $this->clean($expediente?->diagnostico);
        if ($has('treatment_plan')) $context['plan_tratamiento'] = $this->compact($expediente?->plan_tratamiento ?? []);
        if ($has('medications')) $context['medicacion_reportada'] = $this->clean(data_get($patient->historiaClinica, 'clinical_intake.medicamentos'));
        if ($has('scales')) $context['escalas'] = $this->scales($expediente?->escalas ?? []);
        if ($has('mental_exam')) $context['examen_mental'] = $this->compact($expediente?->examen_mental ?? []);

        if ($has('sessions')) {
            $context['sesiones_recientes'] = Appointment::where('patient', $patient->id)->where('user', $userId)
                ->orderByDesc('start')->limit(8)->get()->map(fn ($session) => $this->compact([
                    'fecha' => optional($session->start)->toDateString(),
                    'objetivo' => $this->clean($session->objective),
                    'descripcion' => $this->clean($session->session_description ?: $session->comments),
                    'intervenciones' => $this->clean($session->interventions),
                    'plan_accion' => $this->clean($session->action_plan),
                    'observaciones' => $this->clean($session->observations),
                    'escalas' => $this->scales($session->psychometric_scales ?? []),
                    'examen_mental' => $this->compact($session->mental_exam ?? []),
                ]))->all();
        }
        if ($has('questionnaires')) {
            $context['cuestionarios'] = QuestionnaireLink::where('patient', $patient->id)->where('user', $userId)
                ->with(['questionnaire:id,title', 'questionnaireLink'])->latest()->limit(6)->get()->map(fn ($link) => $this->compact([
                    'titulo' => $link->questionnaire?->title,
                    'estado' => $link->questionnaireLink?->status,
                    'respuesta' => $this->clean($link->questionnaireLink?->response),
                ]))->all();
        }
        if ($has('emotion_diary')) {
            $context['diario_emocional'] = EmotionLog::where('patient_id', $patient->id)->latest()->limit(8)->get()->map(fn ($log) => $this->compact([
                'emocion' => $log->emotion ?: $log->feeling,
                'intensidad' => $log->intensity,
                'situacion' => $this->clean($log->situation),
                'respuesta_adaptativa' => $this->clean($log->adaptive_response),
            ]))->all();
        }

        return $this->compact($context);
    }

    private function scales(array $scales): array
    {
        return collect($scales)->take(12)->map(fn ($scale) => $this->compact([
            'nombre' => Arr::get($scale, 'label', Arr::get($scale, 'name')),
            'puntaje' => Arr::get($scale, 'score'),
            'maximo' => Arr::get($scale, 'max_score', Arr::get($scale, 'scoring.max')),
            'interpretacion' => Arr::get($scale, 'interpretation'),
        ]))->filter()->values()->all();
    }

    private function compact($value)
    {
        if (! is_array($value)) return $this->clean($value);
        return collect($value)->map(fn ($item) => is_array($item) ? $this->compact($item) : $this->clean($item))
            ->filter(fn ($item) => ! ($item === null || $item === '' || $item === []))->all();
    }

    private function clean($value): ?string
    {
        if (is_array($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_scalar($value) || ! filled($value)) return null;
        return Str::limit(trim(strip_tags((string) $value)), 1800, '');
    }

    private function age($date): ?int
    {
        try { return $date ? now()->diffInYears($date) : null; } catch (\Throwable) { return null; }
    }

    private function serialize(AiPatientSummary $summary): array
    {
        return [
            'id' => $summary->id,
            'recipient' => $summary->recipient,
            'title' => $summary->title,
            'content' => $summary->content,
            'sections' => $summary->included_sections,
            'instructions' => $summary->instructions,
            'created_at' => optional($summary->created_at)->toISOString(),
            'updated_at' => optional($summary->updated_at)->toISOString(),
        ];
    }
}
