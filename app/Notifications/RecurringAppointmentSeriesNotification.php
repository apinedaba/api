<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecurringAppointmentSeriesNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Appointment $firstAppointment,
        protected string $frequency,
        protected string $until,
        protected int $occurrencesCount
    ) {}

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isProfessional = $notifiable instanceof User;
        $start = Carbon::parse($this->firstAppointment->start)->timezone(config('app.timezone'));
        $until = Carbon::parse($this->until)->timezone(config('app.timezone'));
        $patient = $this->firstAppointment->patient()->first();
        $professional = $this->firstAppointment->user()->first();
        $counterpart = $isProfessional
            ? ($patient?->name ?: 'tu paciente')
            : ($professional?->name ?: 'tu profesional');

        return (new MailMessage)
            ->subject('MindMeet | Serie de sesiones programada')
            ->greeting('Hola ' . ($notifiable->name ?? ''))
            ->line(
                $isProfessional
                    ? "Se creo una serie {$this->frequencyLabel()} con {$counterpart}."
                    : "Se programo una serie {$this->frequencyLabel()} con {$counterpart}."
            )
            ->line('Primera sesion: ' . $start->translatedFormat('d \\d\\e F \\d\\e Y \\a \\l\\a\\s H:i'))
            ->line('Vigencia hasta: ' . $until->translatedFormat('d \\d\\e F \\d\\e Y'))
            ->line('Total de sesiones generadas: ' . $this->occurrencesCount)
            ->action(
                $isProfessional ? 'Ver agenda' : 'Ver dashboard',
                $isProfessional
                    ? $this->professionalAgendaUrl()
                    : rtrim(config('app.perfil_paciente_url'), '/') . '/dashboard'
            );
    }

    public function toArray(object $notifiable): array
    {
        $isProfessional = $notifiable instanceof User;
        $patient = $this->firstAppointment->patient()->first();
        $professional = $this->firstAppointment->user()->first();
        $counterpart = $isProfessional
            ? ($patient?->name ?: 'tu paciente')
            : ($professional?->name ?: 'tu profesional');

        return [
            'title' => 'Serie recurrente creada',
            'body' => ($isProfessional ? "Se genero" : "Se programo")
                . " una reunion {$this->frequencyLabel()} con {$counterpart} con vigencia hasta el {$this->formattedUntil()}.",
            'action_url' => $isProfessional
                ? $this->professionalAgendaUrl()
                : rtrim(config('app.perfil_paciente_url'), '/') . '/dashboard',
            'action_label' => $isProfessional ? 'Abrir agenda' : 'Ver sesiones',
            'kind' => 'appointment-series-created',
            'appointment_id' => $this->firstAppointment->id,
            'recurrence_id' => $this->firstAppointment->recurrence_id,
            'occurrences_count' => $this->occurrencesCount,
            'until' => $this->until,
        ];
    }

    protected function frequencyLabel(): string
    {
        return match (strtoupper($this->frequency)) {
            'DAILY' => 'diaria',
            'MONTHLY' => 'mensual',
            default => 'semanal',
        };
    }

    protected function formattedUntil(): string
    {
        return Carbon::parse($this->until)->translatedFormat('d/m/Y');
    }

    protected function professionalAgendaUrl(): string
    {
        return rtrim(config('app.front_url_psicologo') ?: config('app.front_url_user') ?: config('app.front_url'), '/') . '/agenda';
    }
}
