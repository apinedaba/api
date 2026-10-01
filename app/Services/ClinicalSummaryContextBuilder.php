<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\EmotionLog;
use App\Models\Expediente;
use App\Models\Patient;
use App\Models\QuestionnaireLink;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ClinicalSummaryContextBuilder
{
    public function build(Patient $patient, int $psychologistId, array $sections): array
    {
        $expediente = Expediente::where('patient_id', $patient->id)->where('user_id', $psychologistId)->first();
        $allowed = array_flip($sections);
        $context = [];

        if (isset($allowed['profile'])) $context['perfil'] = $this->compact([
            'edad' => $this->age(data_get($patient->relevantes, 'fechaNac')),
            'genero' => data_get($patient->relevantes, 'genero') ?: data_get($patient->relevantes, 'sexo'),
            'ocupacion' => data_get($patient->relevantes, 'ocupacion'),
            'estado_civil' => data_get($patient->relevantes, 'estadoCivil'),
        ]);
        if (isset($allowed['intake'])) $context['admision'] = $this->compact([
            'motivo_consulta' => $expediente?->motivoConsulta ?: data_get($patient->historiaClinica, 'clinical_intake.motivo_consulta'),
            'antecedentes' => $expediente?->antecedentes,
            'terapia_previa' => data_get($patient->historiaClinica, 'clinical_intake.terapia_psicologica_detalle'),
        ]);
        if (isset($allowed['diagnosis'])) $context['diagnostico_documentado'] = $this->clean($expediente?->diagnostico);
        if (isset($allowed['treatment_plan'])) $context['plan_tratamiento'] = $this->compact($expediente?->plan_tratamiento ?? []);
        if (isset($allowed['medications'])) $context['medicacion_reportada'] = $this->clean(data_get($patient->historiaClinica, 'clinical_intake.medicamentos'));
        if (isset($allowed['scales'])) $context['escalas'] = $this->scales($expediente?->escalas ?? []);
        if (isset($allowed['mental_exam'])) $context['examen_mental'] = $this->compact($expediente?->examen_mental ?? []);
        if (isset($allowed['sessions'])) $context['sesiones_recientes'] = Appointment::where('patient', $patient->id)->where('user', $psychologistId)
            ->orderByDesc('start')->limit(8)->get()->sortBy('start')->values()->map(fn ($session) => $this->compact([
                'fecha' => optional($session->start)->toDateString(), 'objetivo' => $this->clean($session->objective),
                'descripcion' => $this->clean($session->session_description ?: $session->comments), 'intervenciones' => $this->clean($session->interventions),
                'plan_accion' => $this->clean($session->action_plan), 'observaciones' => $this->clean($session->observations),
                'escalas' => $this->scales($session->psychometric_scales ?? []), 'examen_mental' => $this->compact($session->mental_exam ?? []),
            ]))->all();
        if (isset($allowed['questionnaires'])) $context['cuestionarios'] = QuestionnaireLink::where('patient', $patient->id)->where('user', $psychologistId)
            ->with(['questionnaire:id,title', 'questionnaireLink'])->latest()->limit(6)->get()->map(fn ($link) => $this->compact([
                'titulo' => $link->questionnaire?->title, 'estado' => $link->questionnaireLink?->status,
                'respuesta' => $this->clean($link->questionnaireLink?->response),
            ]))->all();
        if (isset($allowed['emotion_diary'])) $context['diario_emocional'] = EmotionLog::where('patient_id', $patient->id)->latest()->limit(8)->get()->map(fn ($log) => $this->compact([
            'emocion' => $log->emotion ?: $log->feeling, 'intensidad' => $log->intensity,
            'situacion' => $this->clean($log->situation), 'respuesta_adaptativa' => $this->clean($log->adaptive_response),
        ]))->all();

        return $this->compact($context);
    }

    private function scales(array $scales): array { return collect($scales)->take(12)->map(fn ($scale) => $this->compact(['nombre' => Arr::get($scale, 'label', Arr::get($scale, 'name')), 'puntaje' => Arr::get($scale, 'score'), 'maximo' => Arr::get($scale, 'max_score', Arr::get($scale, 'scoring.max')), 'interpretacion' => Arr::get($scale, 'interpretation')]))->filter()->values()->all(); }
    private function compact($value) { if (!is_array($value)) return $this->clean($value); return collect($value)->map(fn ($item) => is_array($item) ? $this->compact($item) : $this->clean($item))->filter(fn ($item) => !($item === null || $item === '' || $item === []))->all(); }
    private function clean($value): ?string { if (is_array($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); return !is_scalar($value) || !filled($value) ? null : Str::limit(trim(strip_tags((string) $value)), 1800, ''); }
    private function age($date): ?int { try { return $date ? now()->diffInYears($date) : null; } catch (\Throwable) { return null; } }
}
