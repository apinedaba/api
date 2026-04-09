<?php

namespace App\Notifications;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfessionalAppointmentStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Appointment $appointment,
        protected string $status
    ) {
    }

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $patient = $this->appointment->patient()->first();
        $start = Carbon::parse($this->appointment->start)->timezone(config('app.timezone'));

        return (new MailMessage)
            ->subject('MindMeet | Actualizacion de sesion')
            ->greeting('Hola ' . ($notifiable->name ?? ''))
            ->line(($patient?->name ?: 'Tu paciente') . ' actualizo el estado de la sesion.')
            ->line('Nuevo estado: ' . $this->statusLabel())
            ->line('Fecha: ' . $start->translatedFormat('d \\d\\e F \\d\\e Y'))
            ->line('Hora: ' . $start->format('H:i'))
            ->action('Ver agenda', rtrim(config('app.front_url_user') ?: config('app.front_url'), '/') . '/agenda');
    }

    public function toArray(object $notifiable): array
    {
        $patient = $this->appointment->patient()->first();

        return [
            'title' => 'Actualizacion en una sesion',
            'body' => ($patient?->name ?: 'Tu paciente') . ' cambio el estado de la sesion a ' . $this->statusLabel() . '.',
            'action_url' => rtrim(config('app.front_url_user') ?: config('app.front_url'), '/') . '/agenda',
            'action_label' => 'Ver agenda',
            'kind' => 'appointment-status-professional',
            'appointment_id' => $this->appointment->id,
            'status' => $this->status,
        ];
    }

    protected function statusLabel(): string
    {
        return match (strtolower($this->status)) {
            'confirmed', 'confirm', 'confirmado' => 'confirmada',
            'cancel', 'cancelado', 'cancelada' => 'cancelada',
            default => $this->status,
        };
    }
}
