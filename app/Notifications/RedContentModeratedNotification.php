<?php

namespace App\Notifications;

use App\Models\RedPregunta;
use App\Models\RedRespuesta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class RedContentModeratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly RedPregunta|RedRespuesta $content,
        private readonly string $action
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $isQuestion = $this->content instanceof RedPregunta;
        $isHidden = $this->action === 'hidden';
        $contentLabel = $isQuestion ? 'pregunta' : 'respuesta';
        $questionId = $isQuestion ? $this->content->id : $this->content->pregunta_id;

        return [
            'title' => $isHidden ? 'Contenido ocultado en Mentes en Red' : 'Contenido restaurado en Mentes en Red',
            'body' => $isHidden
                ? "Tu {$contentLabel} fue ocultada después de una revisión de moderación."
                : "Tu {$contentLabel} volvió a estar visible después de una revisión de moderación.",
            'action_url' => config('app.front_url') . '/mentes-en-red?tab=mis-preguntas',
            'action_label' => 'Ir a Mentes en Red',
            'kind' => 'community_moderation',
            'question_id' => $questionId,
            'action' => $this->action,
        ];
    }
}
