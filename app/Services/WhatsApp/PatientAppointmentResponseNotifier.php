<?php

namespace App\Services\WhatsApp;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\ProfessionalAppointmentStatusNotification;
use App\Services\NotificationPreferenceService;
use App\Support\ProfessionalContact;
use Carbon\Carbon;

class PatientAppointmentResponseNotifier
{
    public function __construct(
        private WhatsAppService $whatsApp,
        private NotificationPreferenceService $preferences,
    ) {
    }

    /**
     * Envía WhatsApp al profesional solo si configuró el canal y el evento
     * tiene una plantilla Meta activa. El correo e inbox se emiten por la
     * notificación normal de estado.
     */
    public function send(Appointment $appointment, string $status): bool
    {
        $appointment->loadMissing(['patient', 'user']);
        $professional = $appointment->getRelation('user');
        $patient = $appointment->getRelation('patient');

        if (! $professional instanceof User) {
            return false;
        }

        $notification = new ProfessionalAppointmentStatusNotification($appointment, $status);
        if (! $this->preferences->enabled($professional, $notification, 'whatsapp')) {
            return false;
        }

        $start = Carbon::parse($appointment->start)->timezone(config('app.timezone'));

        return $this->whatsApp->queueConfiguredProfessionalTemplate(
            'appointment_patient_response',
            $professional,
            [
                'patient_name' => ProfessionalContact::templateText((string) ($patient?->name ?: 'Tu paciente')),
                'professional_name' => ProfessionalContact::publicName($professional),
                'appointment_date' => $start->format('d/m/Y'),
                'appointment_time' => $start->format('H:i'),
                'appointment_status' => $this->statusLabel($status),
                'agenda_url' => rtrim(
                    config('app.front_url_psicologo') ?: config('app.front_url_user') ?: config('app.front_url'),
                    '/'
                ) . '/agenda',
            ],
            [
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient,
                'user_id' => $professional->id,
            ],
        );
    }

    private function statusLabel(string $status): string
    {
        return match (strtolower($status)) {
            'confirmed', 'confirm', 'confirmado' => 'confirmó la cita',
            'cancel', 'cancelada', 'cancelado' => 'canceló la cita',
            'reschedule requested', 'reprogramacion solicitada' => 'solicitó reprogramar la cita',
            default => 'actualizó el estado de la cita',
        };
    }
}
