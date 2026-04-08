<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Traits\NotificaInternamente;

class NuevoPacienteBienvenida extends Notification
{
    use Queueable, NotificaInternamente;

    protected $patient;

    public function __construct($patient)
    {
        $this->patient = $patient;
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // ✅ Notificación interna
        $asunto = 'Nuevo paciente registrado en MindMeet';
        $cuerpo = "Nuevo registro de paciente:\n\nNombre: {$this->patient->name}\nCorreo: {$this->patient->email}\n\nEl paciente se registró por su cuenta.";
        $this->enviarNotificacionInterna($this->patient, $asunto, $cuerpo);

        // 📩 Correo de bienvenida para el paciente
        return (new MailMessage)
            ->subject('¡Bienvenido(a) a MindMeet!')
            ->line('Gracias por registrarte en nuestra plataforma.')
            ->line('Estamos aquí para apoyarte en tu camino hacia el bienestar.');
    }
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Bienvenido a MindMeet',
            'body' => 'Tu cuenta ya esta lista para comenzar tu proceso dentro de la plataforma.',
            'action_url' => rtrim(config('app.perfil_paciente_url'), '/') . '/dashboard',
            'action_label' => 'Ir al inicio',
            'kind' => 'welcome',
        ];
    }
}
