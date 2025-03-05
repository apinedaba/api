<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class PatientAssignedEmailNotification extends Notification
{
    use Queueable;
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
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {   
        $url = env('APP_FRONT')."/invitation/enlace/".base64_encode(json_encode(
            ['usuario'=> $this->user->id, 'paciente'=>$this->patient->id, 'enlace'=>$this->enlace->id]
        ));
        return (new MailMessage)
                    ->subject($this->user->name." esta esperando tu confirmación")
                    ->view('email.acceptInvitation', ['usuario'=> $this->user, 'paciente'=>$this->patient, 'url' => $url]);
    }
}
