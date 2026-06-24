<?php

namespace App\Notifications;

use App\Models\Patient;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PatientInvitationWhatsAppNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Patient $patient,
        protected ?string $url = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsApp(object $notifiable): array
    {
        return [
            'message_type' => 'template',
            'phone' => $notifiable->phone ?? $this->patient->phone,
            'template' => config('services.whatsapp.templates.patient_invitation', 'patient_invitation'),
            'language' => 'es_MX',
            'parameters' => [
                $this->patient->name,
                $this->url ?: rtrim(config('app.perfil_paciente_url'), '/').'/dashboard',
            ],
            'context' => [
                'patient_id' => $this->patient->id,
            ],
        ];
    }
}
