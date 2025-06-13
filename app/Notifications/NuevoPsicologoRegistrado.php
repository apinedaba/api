<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;


class NuevoPsicologoRegistrado extends Notification
{
    use Queueable;
    protected $user;
    /**
     * Create a new notification instance.
     */
    public function __construct($user)
    {
        $this->user = $user;
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
        // Enviar copia reducida a tus correos
        $this->enviarNotificacionInterna($this->user);

        // Correo normal al usuario
        return (new MailMessage)
            ->subject('¡Te damos la bienvenida a MindMeet!')
            ->view('email.registro', ['usuario' => $this->user]);
    }

    protected function enviarNotificacionInterna($user)
    {
        $correosInternos = ['jhernandez961116@gmail.com', 'apinedabawork@gmail.com'];

        foreach ($correosInternos as $correo) {
            Mail::raw("Nuevo registro:\nNombre: {$user->name}\nCorreo: {$user->email}", function ($message) use ($correo) {
                $message->to($correo)
                    ->subject('Nuevo usuario registrado en MindMeet');
            });
        }
    }
}
