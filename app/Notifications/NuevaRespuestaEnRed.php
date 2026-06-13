<?php

namespace App\Notifications;

use App\Models\RedPregunta;
use App\Models\RedRespuesta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NuevaRespuestaEnRed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly RedPregunta $question,
        private readonly RedRespuesta $answer
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Nueva respuesta en Mentes en Red',
            'body' => "{$this->answer->autor->name} respondió: {$this->question->titulo}",
            'action_url' => config('app.front_url') . '/mentes-en-red?tab=preguntas',
            'action_label' => 'Ver respuesta',
            'kind' => 'community_answer',
            'pregunta_id' => $this->question->id,
            'respuesta_id' => $this->answer->id,
        ];
    }
}
