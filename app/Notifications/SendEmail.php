<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use App\Notifications\Traits\NotificaInternamente;


class SendEmail extends Notification
{
    use Queueable, NotificaInternamente;
    protected $_subject;
    protected $_body;
    protected $_patient;

    /**
     * Create a new notification instance.
     */
    public function __construct($subject, $body, $patient)
    {
        $this->_subject = $subject;
        $this->_body = $body;
        $this->_patient = $patient;
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
        $asunto = $this->_subject;
        $cuerpo = $this->_body;
        $patient = $this->_patient;

        return (new MailMessage)
            ->subject($asunto)
            ->view('email.patient-email-notification', ['asunto' => $asunto, 'body' => $cuerpo, 'patient' => $patient]);
    }
}
