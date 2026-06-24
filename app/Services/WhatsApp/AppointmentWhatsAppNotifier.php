<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;

class AppointmentWhatsAppNotifier
{
    public function __construct(protected WhatsAppService $whatsApp)
    {
    }

    public function appointmentCreated(Appointment $appointment, string $source = 'appointments.create'): bool
    {
        return $this->dispatchAppointmentTemplate($appointment, 'appointment_created', $source);
    }

    public function appointmentReminder(Appointment $appointment, string $source = 'appointments.reminder'): bool
    {
        return $this->dispatchAppointmentTemplate($appointment, 'appointment_reminder', $source);
    }

    public function appointmentCancelled(Appointment $appointment, string $source = 'appointments.cancelled'): bool
    {
        return $this->dispatchAppointmentTemplate($appointment, 'appointment_cancelled', $source);
    }

    protected function dispatchAppointmentTemplate(Appointment $appointment, string $templateKey, string $source): bool
    {
        $appointment->loadMissing(['patient', 'user']);
        $patient = $appointment->patient()->first();

        Log::channel('whatsapp')->info('WhatsApp appointment notification flow started', [
            'source' => $source,
            'event' => $templateKey,
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient,
            'user_id' => $appointment->user,
            'has_patient' => (bool) $patient,
            'has_patient_phone' => filled($patient?->phone),
        ]);

        if (! $patient) {
            Log::channel('whatsapp')->warning('WhatsApp appointment notification skipped: patient not found', [
                'source' => $source,
                'event' => $templateKey,
                'appointment_id' => $appointment->id,
            ]);

            return false;
        }

        if (! filled($patient->phone)) {
            Log::channel('whatsapp')->warning('WhatsApp appointment notification skipped: missing patient phone', [
                'source' => $source,
                'event' => $templateKey,
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
            ]);

            return false;
        }

        $template = $this->whatsApp->templateName($templateKey);

        SendWhatsAppMessageJob::dispatch([
            'message_type' => 'template',
            'phone' => $patient->phone,
            'template' => $template,
            'language' => 'es_MX',
            'components' => $this->whatsApp->appointmentTemplateComponents(
                $appointment,
                $this->defaultAppointmentButtons($appointment)
            ),
            'context' => [
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
                'user_id' => $appointment->user,
                'event' => $templateKey,
                'source' => $source,
            ],
        ]);

        Log::channel('whatsapp')->info('WhatsApp appointment notification queued', [
            'source' => $source,
            'event' => $templateKey,
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'template' => $template,
        ]);

        return true;
    }

    protected function defaultAppointmentButtons(Appointment $appointment): array
    {
        return [
            [
                'id' => "appointment_{$appointment->id}_confirm",
                'payload' => "appointment:{$appointment->id}:confirm",
            ],
            [
                'id' => "appointment_{$appointment->id}_postpone",
                'payload' => "appointment:{$appointment->id}:postpone",
            ],
            [
                'id' => "appointment_{$appointment->id}_cancel",
                'payload' => "appointment:{$appointment->id}:cancel",
            ],
        ];
    }
}
