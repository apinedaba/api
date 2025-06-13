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
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        return (new MailMessage)
            ->subject('Estado de tu cita en MindMeet')
            ->view('email.stateAppointment', [
                'usuario' => $this->usuario,
                'estado' => $this->estado,
                'fecha' => $this->fecha,
                'hora' => $this->hora,
            ]);
    }
}
