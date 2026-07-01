<?php

namespace App\Notifications;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfessionalAppointmentCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Appointment $appointment)
    {
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
            ->subject('MindMeet | Nueva sesion programada')
            ->view('email.professional-appointment-created', [
                'name' => $notifiable->name ?? '',
                'patientName' => $patient?->name ?: 'tu paciente',
                'date' => $start->translatedFormat('d \\d\\e F \\d\\e Y'),
                'time' => $start->format('H:i'),
                'agendaUrl' => $this->professionalAgendaUrl(),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $patient = $this->appointment->patient()->first();
        $start = Carbon::parse($this->appointment->start)->timezone(config('app.timezone'));

        return [
            'title' => 'Nueva sesion programada',
            'body' => "Tienes una nueva sesion con " . ($patient?->name ?: 'tu paciente') . " el {$start->format('d/m/Y')} a las {$start->format('H:i')}.",
            'action_url' => $this->professionalAgendaUrl(),
            'action_label' => 'Abrir agenda',
            'kind' => 'appointment-created-professional',
            'appointment_id' => $this->appointment->id,
        ];
    }

    protected function professionalAgendaUrl(): string
    {
        return rtrim(config('app.front_url_psicologo') ?: config('app.front_url_user') ?: config('app.front_url'), '/') . '/agenda';
    }
}
