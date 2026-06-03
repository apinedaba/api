<?php

namespace App\Notifications;

use App\Models\Patient;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PatientConsentSignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Patient $patient,
        protected array $consent
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Consentimiento firmado',
            'body' => ($this->patient->name ?: 'Tu paciente') . ' firmo el consentimiento informado digital.',
            'action_url' => $this->patientUrl(),
            'action_label' => 'Abrir expediente',
            'kind' => 'patient-consent-signed',
            'patient_id' => $this->patient->id,
            'patient_name' => $this->patient->name,
            'consent' => [
                'status' => data_get($this->consent, 'status', 'signed'),
                'type' => data_get($this->consent, 'type', 'digital'),
                'signed_at' => data_get($this->consent, 'signed_at'),
                'signed_patient_name' => data_get($this->consent, 'signed_patient_name'),
                'public_signed_at' => data_get($this->consent, 'public_signed_at'),
            ],
        ];
    }

    protected function patientUrl(): string
    {
        return rtrim(config('app.front_url_psicologo') ?: config('app.front_url_user') ?: config('app.front_url'), '/') . '/pacientes/' . $this->patient->id . '?tab=15';
    }
}
