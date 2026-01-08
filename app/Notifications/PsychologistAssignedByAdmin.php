<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use App\Notifications\Traits\NotificaInternamente;

class PsychologistAssignedByAdmin extends Notification
{
    use Queueable, NotificaInternamente;
    
    protected $patient;
    protected $admin;
    protected $isActive;

    /**
     * Create a new notification instance.
     */
    public function __construct($patient, $admin, $isActive = false)
    {
        $this->patient = $patient;
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
        $asunto = 'Nuevo paciente asignado por administrador en MindMeet';
        $cuerpo = "Se ha asignado un nuevo paciente al psicólogo {$notifiable->name}:\n\nPaciente: {$this->patient->name}\nCorreo: {$this->patient->email}\n\nAsignado por: {$this->admin->name} ({$this->admin->email})\nEstado: " . ($this->isActive ? 'Activo' : 'Inactivo');
        $this->enviarNotificacionInterna($notifiable, $asunto, $cuerpo);

        $url = env('APP_FRONT') . "/paciente/" . $this->patient->id;

        return (new MailMessage)
            ->subject('Nuevo paciente asignado - ' . $this->patient->name)
            ->view('email.psychologist-assigned-patient', [
                'psychologist' => $notifiable,
                'patient' => $this->patient,
                'admin' => $this->admin,
                'isActive' => $this->isActive,
                'url' => $url
            ]);
    }
}
