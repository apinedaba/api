<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use App\Notifications\Traits\NotificaInternamente;

class PatientAssignedPsychologistByAdmin extends Notification
{
    use Queueable, NotificaInternamente;

    protected $psychologist;
    protected $admin;
    protected $isActive;

    /**
     * Create a new notification instance.
     */
    public function __construct($psychologist, $admin, $isActive = false)
    {
        $this->psychologist = $psychologist;
        $this->admin = $admin;
        $this->isActive = $isActive;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // ✅ Notificación interna
        $asunto = 'Psicólogo asignado a paciente por administrador en MindMeet';
        $cuerpo = "Se ha asignado el psicólogo {$this->psychologist->name} al paciente {$notifiable->name}:\n\nPsicólogo: {$this->psychologist->name}\nCorreo: {$this->psychologist->email}\n\nAsignado por: {$this->admin->name} ({$this->admin->email})\nEstado: " . ($this->isActive ? 'Activo' : 'Inactivo');
        $this->enviarNotificacionInterna($notifiable, $asunto, $cuerpo);

        $url = config('app.front_url') . "/perfil";

        return (new MailMessage)
            ->subject('Nuevo psicólogo asignado - ' . $this->psychologist->name)
            ->view('email.patient-assigned-psychologist', [
                'patient' => $notifiable,
                'psychologist' => $this->psychologist,
                'admin' => $this->admin,
                'isActive' => $this->isActive,
                'url' => $url
            ]);
    }
}
