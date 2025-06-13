<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CreateAppoinmentMail extends Notification
{
    use Queueable;

    protected $appointment;
    protected $patient;
    protected $user;
    /**
     * Create a new notification instance.
     */
    public function __construct($appointment, $patient)
    {
        $this->appointment = $appointment;
        $this->patient = $patient;
        $this->user = auth()->user();
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
        return (new MailMessage)
                    ->subject('Nueva cita creada en MindMeet')
                    ->view('email.createAppointment', [
                        'cita' => $this->appointment,
                        'paciente' => $this->patient,
                        'pacienteName' => $notifiable->name,
                        'user' => $this->user,
                    ])
                    ->action('Confirmar cita', url('/'))
                    ->line('Gracias por usar nuestra aplicación!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
