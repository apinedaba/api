<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Traits\NotificaInternamente;

class NuevoContacto extends Notification
{
    use Queueable, NotificaInternamente;

    protected $patient;

    public function __construct($patient)
    {
        $this->patient = $patient;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {

        $asunto = 'Nuevo contacto para dar información';
        $cuerpo = "Nuevo contacto de paciente:\n\nNombre: {$this->patient->name}\nCorreo: {$this->patient->email}";
        $this->enviarNotificacionInterna($this->patient, $asunto, $cuerpo);

        // 📩 Correo de bienvenida para el paciente
        return (new MailMessage)
            ->subject('¡Bienvenido(a) a MindMeet!')
            ->line('Gracias por mandar tu infomación en nuestra plataforma.')
            ->line('En breve nos pondremos en contacto contigo.')
            ->line('Estamos aquí para apoyarte en tu camino hacia el bienestar.');
    }
}
