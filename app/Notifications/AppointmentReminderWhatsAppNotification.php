<?php

namespace App\Notifications;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppointmentReminderWhatsAppNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Appointment $appointment,
        protected string $reminderKey = '30m'
    ) {
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
            'template' => 'appointment_reminder',
            'language' => 'es_MX',
            'parameters' => [
                $notifiable->name ?? 'paciente',
                $start->format('d/m/Y H:i'),
                $professional?->name ?: 'tu profesional',
                $this->humanReminderLabel(),
            ],
            'context' => [
                'appointment_id' => $this->appointment->id,
                'reminder_key' => $this->reminderKey,
            ],
        ];
    }

    protected function humanReminderLabel(): string
    {
        return match ($this->reminderKey) {
            '24h' => 'en aproximadamente 24 horas',
            '30m' => 'en 30 minutos',
            default => 'proximamente',
        };
    }
}
