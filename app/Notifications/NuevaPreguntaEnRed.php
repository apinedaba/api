<?php

namespace App\Notifications;

use App\Models\RedPregunta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Traits\NotificaInternamente;

class NuevaPreguntaEnRed extends Notification implements ShouldQueue
{
    use Queueable, NotificaInternamente;

    protected $pregunta;
    protected $autor;

    /**
     * Create a new notification instance.
     */
    public function __construct(RedPregunta $pregunta)
    {
        $this->pregunta = $pregunta;
        $this->autor = $pregunta->autor;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title'       => 'Nueva pregunta en Mentes en Red',
            'body'        => "{$this->autor->name} preguntó: {$this->pregunta->titulo}",
            'action_url'  => config('app.front_url') . '/mentes-en-red?tab=preguntas',
            'action_label' => 'Ver pregunta',
            'kind'        => 'community_question',
            'pregunta_id' => $this->pregunta->id,
            'autor_id'    => $this->autor->id,
            'autor_name'  => $this->autor->name,
        ];
    }
}
