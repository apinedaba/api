<?php

namespace App\Notifications;

use App\Models\MinderConsultationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MinderConsultationRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly MinderConsultationRequest $consultation,
        private readonly string $event
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $isRequested = $this->event === 'requested';
        $counterpart = $isRequested ? $this->consultation->sender : $this->consultation->recipient;

        $body = match ($this->event) {
            'accepted' => "{$counterpart->name} aceptó tu solicitud: {$this->consultation->subject}",
            'rejected' => "{$counterpart->name} no aceptó tu solicitud: {$this->consultation->subject}",
            default => "{$counterpart->name} quiere consultarte: {$this->consultation->subject}",
        };

        return [
            'title' => $isRequested ? 'Nueva solicitud de consulta' : 'Actualización de consulta',
            'body' => $body,
            'action_url' => config('app.front_url') . '/mentes-en-red?tab=consultas',
            'action_label' => 'Ver consultas',
            'kind' => 'direct_consultation',
            'consultation_request_id' => $this->consultation->id,
            'status' => $this->consultation->status,
        ];
    }
}
