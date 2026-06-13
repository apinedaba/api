<?php

namespace App\Notifications;

use App\Models\Administrator;
use App\Models\MinderSupportAppointment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MinderSupportAppointmentNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected MinderSupportAppointment $appointment,
        protected string $event = 'created'
    ) {
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof User || $notifiable instanceof Administrator ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment->fresh('user') ?? $this->appointment->loadMissing('user');
        $date = $appointment->scheduled_at->timezone(config('app.timezone'));
        $isInternal = ! $notifiable instanceof User;
        $psychologistName = $appointment->user?->name ?? 'Psicólogo';
        $psychologistEmail = $appointment->user?->email ?? 'Sin correo registrado';
        $subject = match ($this->event) {
            'requested' => 'MindMeet | Solicitud de apoyo recibida',
            'confirmed' => 'MindMeet | Sesión de apoyo confirmada',
            'rescheduled' => 'MindMeet | Nuevo horario para tu sesión de apoyo',
            'cancelled' => 'MindMeet | Sesión de apoyo cancelada',
            'completed' => 'MindMeet | Sesión de apoyo completada',
            'updated' => 'MindMeet | Sesión de apoyo actualizada',
            default => 'MindMeet | Nueva sesión de apoyo',
        };
        $intro = match ($this->event) {
            'requested' => $isInternal
                ? $psychologistName . ' solicitó una sesión de apoyo.'
                : 'Recibimos tu solicitud. El equipo MindMeet confirmará el horario o propondrá uno nuevo.',
            'confirmed' => 'Tu sesión de apoyo fue confirmada por el equipo MindMeet.',
            'rescheduled' => 'El equipo MindMeet propuso un nuevo horario para tu sesión de apoyo.',
            'cancelled' => 'Tu sesión de apoyo fue cancelada.',
            'completed' => 'Tu sesión de apoyo fue marcada como completada.',
            default => 'Tu sesión de apoyo fue actualizada.',
        };

        return (new MailMessage)
            ->subject($subject)
            ->view('email.minder-support-appointment', [
                'appointment' => $appointment,
                'date' => $date,
                'event' => $this->event,
                'intro' => $intro,
                'isInternal' => $isInternal,
                'notifiableName' => $notifiable->name ?? null,
                'psychologistName' => $psychologistName,
                'psychologistEmail' => $psychologistEmail,
                'supportUrl' => route('minder.support-appointments.index'),
                'topicLabel' => $this->topicLabel($appointment->topic),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $appointment = $this->appointment->fresh('user') ?? $this->appointment->loadMissing('user');
        $isInternal = $notifiable instanceof Administrator;

        return [
            'title' => 'Sesión de apoyo MindMeet',
            'body' => $isInternal
                ? ($appointment->user?->name ?? 'Un psicólogo') . ' ' . match ($this->event) {
                    'cancelled' => 'canceló una sesión de apoyo MindMeet.',
                    default => 'solicitó apoyo MindMeet para ' . $appointment->scheduled_at->timezone(config('app.timezone'))->format('d/m/Y H:i') . '.',
                }
                : 'Tu sesión de apoyo fue ' . match ($this->event) {
                    'requested' => 'solicitada. Te notificaremos al confirmarla.',
                    'confirmed' => 'confirmada.',
                    'rescheduled' => 'reprogramada.',
                    'cancelled' => 'cancelada.',
                    'completed' => 'completada.',
                    'updated' => 'actualizada.',
                    default => 'actualizada.',
                },
            'kind' => 'minder-support-appointment',
            'appointment_id' => $this->appointment->id,
        ];
    }

    private function topicLabel(string $topic): string
    {
        return [
            'configuration' => 'Configuración de cuenta',
            'clinic' => 'Clínicas y equipo',
            'payments' => 'Pagos y suscripción',
            'marketing' => 'Marketing y campañas',
            'training' => 'Capacitación',
            'other' => 'Otro',
        ][$topic] ?? $topic;
    }
}
