<?php

namespace App\Notifications;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppointmentCancelledWhatsAppNotification extends Notification
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
        $this->appointment->loadMissing(['patient', 'user']);
        $professional = $this->appointment->user()->first();
        $start = Carbon::parse($this->appointment->start)->timezone(config('app.timezone'));

        return [
            'message_type' => 'template',
            'phone' => $notifiable->phone,
            'template' => config('services.whatsapp.templates.appointment_cancelled', 'appointment_cancelled'),
            'language' => 'es_MX',
            'parameters' => [
                $notifiable->name ?? 'paciente',
                $start->format('d/m/Y'),
                $start->format('H:i'),
                $professional?->name ?: 'tu profesional',
            ],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $notifiable->name ?? 'paciente'],
                        ['type' => 'text', 'text' => $start->format('d/m/Y')],
                        ['type' => 'text', 'text' => $start->format('H:i')],
                        ['type' => 'text', 'text' => $professional?->name ?: 'tu profesional'],
                    ],
                ],
            ],
            'context' => [
                'appointment_id' => $this->appointment->id,
            ],
        ];
    }
}
