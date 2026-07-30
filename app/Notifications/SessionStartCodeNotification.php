<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Services\SessionStartCodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionStartCodeNotification extends Notification
{
    use Queueable;

    public function __construct(private Appointment $appointment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $code = app(SessionStartCodeService::class)->reveal($this->appointment);

        return (new MailMessage)
            ->subject('MindMeet | Código para iniciar tu sesión')
            ->greeting("Hola {$notifiable->name}")
            ->line('Comparte este código con tu psicólogo al comenzar la sesión:')
            ->line($code)
            ->line('El código es exclusivo de esta sesión. No lo compartas antes de iniciar.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'session_start_code',
            'appointment_id' => $this->appointment->id,
            'title' => 'Código para iniciar tu sesión',
            'message' => 'Tu código de inicio ya está disponible en el detalle de la sesión.',
        ];
    }
}
