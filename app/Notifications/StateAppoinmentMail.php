<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class StateAppoinmentMail extends Notification implements ShouldQueue
{
    use Queueable;

    protected $usuario;
    protected $estado;
    protected $fecha;
    protected $hora;

    public function __construct($usuario, $estado, $fecha, $hora)
    {
        $this->usuario = $usuario;
        $this->estado = $estado;
        $this->fecha = $fecha;
        $this->hora = $hora;
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable)
    {
        return (new MailMessage)
            ->subject("MindMeet | Actualizacion de tu sesion del {$this->fecha}")
            ->view('email.stateAppointment', [
                'usuario' => $this->usuario,
                'estado' => $this->estado,
                'fecha' => $this->fecha,
                'hora' => $this->hora,
            ]);
    }
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Cambio en el estado de tu cita',
            'body' => "Tu cita del {$this->fecha} a las {$this->hora} ahora esta {$this->estado}.",
            'action_url' => rtrim(config('app.perfil_paciente_url'), '/') . '/dashboard',
            'action_label' => 'Ver dashboard',
            'kind' => 'appointment-status',
        ];
    }
}
