<?php

namespace App\Notifications;

use App\Models\Administrator;
use App\Models\BlogPost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BlogPostReviewNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly BlogPost $post,
        private readonly string $event,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $isAdministrator = $notifiable instanceof Administrator;
        $authorName = $this->post->author_name ?: 'Un psicólogo';

        $content = match ($this->event) {
            'submitted' => [
                'title' => 'Artículo enviado a revisión',
                'body' => $isAdministrator
                    ? "{$authorName} envió “{$this->post->title}” para revisión."
                    : "Tu artículo “{$this->post->title}” fue enviado a revisión de MindMeet.",
                'action_label' => $isAdministrator ? 'Revisar artículo' : 'Ver mi Blog',
            ],
            'published' => [
                'title' => 'Artículo aprobado y publicado',
                'body' => "MindMeet aprobó “{$this->post->title}”. Ya está disponible en el Blog.",
                'action_label' => 'Ver artículo',
            ],
            'changes_requested' => [
                'title' => 'Cambios solicitados en tu artículo',
                'body' => "MindMeet solicitó cambios en “{$this->post->title}”: {$this->post->review_feedback}",
                'action_label' => 'Revisar comentarios',
            ],
            default => [
                'title' => 'Actualización de tu artículo',
                'body' => "Hay una actualización en “{$this->post->title}”.",
                'action_label' => 'Ver mi Blog',
            ],
        };

        return [
            ...$content,
            'action_url' => $isAdministrator
                ? url('/blog-posts')
                : ($this->event === 'published'
                    ? rtrim((string) config('app.frontend_url'), '/') . "/blog/{$this->post->slug}"
                    : rtrim((string) config('app.front_url_psicologo'), '/') . '/blog'),
            'kind' => 'blog_review',
            'blog_post_id' => $this->post->id,
            'event' => $this->event,
        ];
    }
}
