<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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

    /**
     * Create a new notification instance.
     */
    public function __construct($appointment, $patient, $hora, $fecha, $interval)
    {
        $this->appointment = $appointment;
        $this->patient = $patient;
        $this->user = auth()->user();
        $this->hora = $hora;
        $this->fecha = $fecha;
        $this->interval = $interval;
    }


    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail', 'database'] : ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nueva cita creada en MindMeet')
            ->view('email.createAppointment', [
                'cita' => $this->appointment,
                'paciente' => $this->patient,
                'pacienteName' => $notifiable->name,
                'user' => $this->user,
                'hora' => $this->hora,
                'fecha' => $this->fecha,
                'interval' => $this->interval->format('%h horas %i minutos'),
                'url' => rtrim(config('app.perfil_paciente_url'), '/') . '/appointments/status/' . rawurlencode(base64_encode(json_encode(['id' => $this->appointment->id]))) . '/Confirmed',

            ])
            ->action('Confirmar cita', url('/'))
            ->line('Gracias por usar nuestra aplicación!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Nueva cita agendada',
            'body' => "Tu cita con {$this->user->name} fue programada para {$this->fecha} a las {$this->hora}.",
            'action_url' => rtrim(config('app.perfil_paciente_url'), '/') . '/appointments/status/' . rawurlencode(base64_encode(json_encode(['id' => $this->appointment->id]))) . '/Confirmed',
            'action_label' => 'Confirmar cita',
            'kind' => 'appointment-created',
        ];
    }
}
