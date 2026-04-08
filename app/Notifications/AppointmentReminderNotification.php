<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Appointment $appointment,
        protected string $reminderKey
    ) {}

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isProfessional = $notifiable instanceof User;
        $counterpart = $this->getCounterpart($notifiable);
        $start = Carbon::parse($this->appointment->start)->timezone(config('app.timezone'));
        $timeLabel = $this->humanReminderLabel();

        return (new MailMessage)
            ->subject($isProfessional ? 'Recordatorio de sesion proxima' : 'Tu sesion comienza pronto')
            ->greeting('Hola ' . ($notifiable->name ?? ''))
            ->line(
                $isProfessional
                    ? "Tienes una sesion con {$counterpart} {$timeLabel}."
                    : "Tu sesion con {$counterpart} {$timeLabel}."
            )
            ->line('Fecha: ' . $start->translatedFormat('d \\d\\e F \\d\\e Y'))
            ->line('Hora: ' . $start->format('H:i'))
            ->action(
                $isProfessional ? 'Ver agenda' : 'Ver sesion',
                $this->actionUrl($notifiable)
            );
    }

    public function toArray(object $notifiable): array
    {
        $isProfessional = $notifiable instanceof User;
        $counterpart = $this->getCounterpart($notifiable);

        return [
            'title' => $isProfessional ? 'Recordatorio de sesion' : 'Tu sesion esta por comenzar',
            'body' => $isProfessional
                ? "Tu sesion con {$counterpart} {$this->humanReminderLabel()}."
                : "Tu sesion con {$counterpart} {$this->humanReminderLabel()}.",
            'action_url' => $this->actionUrl($notifiable),
            'action_label' => $isProfessional ? 'Abrir agenda' : 'Ver sesion',
            'kind' => 'appointment-reminder',
            'appointment_id' => $this->appointment->id,
            'reminder_key' => $this->reminderKey,
        ];
    }

    protected function getCounterpart(object $notifiable): string
    {
        $patient = $this->appointment->patient()->first();
        $professional = $this->appointment->user()->first();

        if ($notifiable instanceof User) {
            return $patient?->name ?: 'tu paciente';
        }

        return $professional?->name ?: 'tu profesional';
    }

    protected function humanReminderLabel(): string
    {
        return match ($this->reminderKey) {
            '24h' => 'comienza en aproximadamente 24 horas',
            '30m' => 'comienza en 30 minutos',
            default => 'esta proxima',
        };
    }

    protected function actionUrl(object $notifiable): string
    {
        if ($notifiable instanceof User) {
            return rtrim(config('app.front_url_user') ?: config('app.front_url'), '/') . '/agenda';
        }

        return rtrim(config('app.perfil_paciente_url'), '/') . '/dashboard';
    }
}
