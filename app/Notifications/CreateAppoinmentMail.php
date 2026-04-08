<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CreateAppoinmentMail extends Notification
{
    use Queueable;

    protected $appointment;
    protected $patient;
    protected $user;
    protected $hora;
    protected $fecha;
    protected $interval;

    public function __construct($appointment, $patient, $hora, $fecha, $interval)
    {
        $this->appointment = $appointment;
        $this->patient = $patient;
        $this->user = auth()->user();
        $this->hora = $hora;
        $this->fecha = $fecha;
        $this->interval = $interval;
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $formato = $this->appointment->cart?->formato
            ?: data_get($this->appointment->extendedProps, 'formato')
            ?: 'online';
        $tipoSesion = $this->appointment->cart?->tipoSesion
            ?: data_get($this->appointment->extendedProps, 'tipoSesion')
            ?: 'Sesion psicologica';

        return (new MailMessage)
            ->subject("MindMeet | Sesion programada para {$this->fecha} a las {$this->hora}")
            ->view('email.createAppointment', [
                'cita' => $this->appointment,
                'paciente' => $this->patient,
                'pacienteName' => $notifiable->name,
                'user' => $this->user,
                'hora' => $this->hora,
                'fecha' => $this->fecha,
                'interval' => $this->interval->format('%h horas %i minutos'),
                'tipoSesion' => $tipoSesion,
                'formato' => ucfirst($formato),
                'isOnline' => strtolower((string) $formato) === 'online',
                'dashboardUrl' => rtrim(config('app.perfil_paciente_url'), '/') . '/dashboard',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Nueva cita agendada',
            'body' => "Tu cita con {$this->user->name} fue programada para {$this->fecha} a las {$this->hora}.",
            'action_url' => rtrim(config('app.perfil_paciente_url'), '/') . '/dashboard',
            'action_label' => 'Ver sesion',
            'kind' => 'appointment-created',
        ];
    }
}
