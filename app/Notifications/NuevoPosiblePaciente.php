<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NotificacionPsicologo extends Notification
{
    use Queueable;

    protected $lead;

    public function __construct($lead)
    {
        $this->lead = $lead;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            // Asunto que verá el psicólogo
            ->subject('🔔 Tienes un nuevo posible paciente en MindMeet')
            ->greeting('¡Hola!')
            ->line('Has recibido una nueva solicitud de contacto a través de tu perfil.')
            ->line('**Detalles del interesado:**')
            ->line('Nombre: ' . $this->lead->name)
            ->line('Correo: ' . $this->lead->email)
            ->line('Mensaje: ' . ($this->lead->mensaje ?? 'Sin mensaje específico.'))
            ->line('Puedes llamarle o mandarle mensaje, su número es ' . ($this->lead->numero ?? 'Sin número.'))
            ->line('O bien, puedes consultar la información completa del lead en tu panel de profesional.')
            // El botón lo lleva directo a su panel de Leads
            ->line('Te recomendamos contactarlo lo antes posible.');
    }
}