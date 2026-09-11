<?php

namespace App\Mail;

use App\Models\MinderSupportAppointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MinderSupportAppointmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MinderSupportAppointment $appointment,
        public string $event,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->event) {
            'requested' => 'MindMeet | Solicitud de apoyo recibida',
            'confirmed' => 'MindMeet | Sesión de apoyo confirmada',
            'cancelled' => 'MindMeet | Sesión de apoyo cancelada',
            default => 'MindMeet | Sesión de apoyo actualizada',
        });
    }

    public function content(): Content
    {
        $appointment = $this->appointment->fresh('user') ?? $this->appointment->loadMissing('user');

        return new Content(view: 'email.minder-support-appointment', with: [
            'appointment' => $appointment,
            'date' => $appointment->scheduled_at->timezone(config('app.timezone')),
            'event' => $this->event,
            'intro' => ($appointment->user?->name ?? 'Un psicólogo') . match ($this->event) {
                'cancelled' => ' canceló una sesión de apoyo.',
                'confirmed' => ' tiene una sesión de apoyo confirmada.',
                default => ' solicitó una sesión de apoyo.',
            },
            'isInternal' => true,
            'notifiableName' => null,
            'psychologistName' => $appointment->user?->name ?? 'Psicólogo',
            'psychologistEmail' => $appointment->user?->email ?? 'Sin correo registrado',
            'supportUrl' => route('minder.support-appointments.index'),
            'topicLabel' => $this->topicLabel($appointment->topic),
        ]);
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
