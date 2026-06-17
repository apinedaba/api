<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use App\Notifications\Traits\NotificaInternamente;


class PatientAssignedEmailNotification extends Notification
{
    use Queueable, NotificaInternamente;
    protected $user;
    protected $patient;
    protected $enlace;

    /**
     * Create a new notification instance.
     */
    public function __construct($user, $patient, $enlace)
    {
        $this->user = $user;
        $this->patient = $patient;
        $this->enlace = $enlace;
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
        // ✅ Notificación interna
        $asunto = 'Nuevo paciente registrado en MindMeet';
        $cuerpo = "Nuevo registro de paciente:\n\nNombre: {$this->patient->name}\nCorreo: {$this->patient->email}\n\nRegistrado por el psicólogo: {$this->user->name} ({$this->user->email})";
        $this->enviarNotificacionInterna($this->patient, $asunto, $cuerpo);
        $url = rtrim(config('app.perfil_paciente_url') ?: 'https://paciente.mindmeet.com.mx', '/') . '/iniciar-sesion';

        return (new MailMessage)
            ->subject($this->user->name . " te agrego a MindMeet")
            ->view('email.acceptInvitation', ['usuario' => $this->user, 'paciente' => $this->patient, 'url' => $url]);
    }
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Tu psicologo te agrego a MindMeet',
            'body' => "{$this->user->name} ya esta disponible como tu profesional asignado.",
            'action_url' => rtrim(config('app.perfil_paciente_url') ?: 'https://paciente.mindmeet.com.mx', '/') . '/iniciar-sesion',
            'action_label' => 'Iniciar sesion',
            'kind' => 'patient-link',
        ];
    }
}
