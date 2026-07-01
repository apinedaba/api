<?php

namespace App\Notifications;

use App\Notifications\Traits\NotificaInternamente;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        $asunto = 'Nuevo paciente registrado en MindMeet';
        $cuerpo = "Nuevo registro de paciente:\n\nNombre: {$this->patient->name}\nCorreo: {$this->patient->email}\n\nEl paciente se registro por su cuenta.";
        $this->enviarNotificacionInterna($this->patient, $asunto, $cuerpo);

        return (new MailMessage)
            ->subject('Bienvenido(a) a MindMeet')
            ->view('email.patient-welcome', [
                'patientName' => $this->patient->name ?? $notifiable->name ?? '',
                'dashboardUrl' => rtrim(config('app.perfil_paciente_url'), '/') . '/dashboard',
            ]);
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
