<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Support\ProfessionalContact;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppointmentRescheduledWhatsAppNotification extends Notification
{
    use Queueable;

    public function __construct(protected Appointment $appointment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsApp(object $notifiable): array
    {
        $this->appointment->loadMissing('user');
        $professional = $this->appointment->getRelation('user');
        $phone = data_get($notifiable->contacto, 'whatsapp') ?: $notifiable->phone;
        $date = Carbon::parse($this->appointment->start)
            ->timezone(config('app.timezone'))
            ->format('d/m/Y');

        return [
            'message_type' => 'template',
            'phone' => $phone,
            'template' => config('services.whatsapp.templates.appointment_rescheduled', 'cita_reprogramada'),
            'language' => 'es_MX',
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => ProfessionalContact::templateText((string) $notifiable->name)],
                        ['type' => 'text', 'text' => $date],
                        ['type' => 'text', 'text' => $professional ? ProfessionalContact::publicName($professional) : 'tu profesional'],
                    ],
                ],
            ],
            'context' => [
                'appointment_id' => $this->appointment->id,
                'patient_id' => $notifiable->id,
                'user_id' => $professional?->id,
                'event' => 'appointment_rescheduled',
            ],
        ];
    }
}
