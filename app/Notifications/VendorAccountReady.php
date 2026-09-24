<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorAccountReady extends Notification
{
    use Queueable;

    public function __construct(private readonly string $activationUrl)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu cuenta de MindMeet está lista')
            ->greeting("Hola {$notifiable->name},")
            ->line('Tu cuenta profesional fue creada por tu asesor de MindMeet.')
            ->line('Para proteger tu acceso, define tu contraseña desde el siguiente enlace. Es personal, de un solo uso y vence en 48 horas.')
            ->action('Activar mi cuenta', $this->activationUrl)
            ->line('Después podrás iniciar sesión y completar tu perfil, horarios y servicios.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Tu cuenta está lista',
            'body' => 'Define tu contraseña para activar tu acceso profesional.',
            'kind' => 'vendor_account_ready',
        ];
    }
}
