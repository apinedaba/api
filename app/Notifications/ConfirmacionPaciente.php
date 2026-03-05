<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmacionPaciente extends Notification
{
    use Queueable;

    public function __construct()
    {
       
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('¡Tu solicitud ha sido enviada! - MindMeet')
            ->view('email.confirmacionPaciente');
    }
}