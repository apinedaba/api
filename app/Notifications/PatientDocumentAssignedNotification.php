<?php

namespace App\Notifications;

use App\Models\PatientDocumentRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PatientDocumentAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected PatientDocumentRequest $document,
        protected User $professional
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Nuevo documento para firma',
            'body' => $this->professional->name.' te solicitó firmar “'.$this->document->title.'”.',
            'action_url' => rtrim(config('app.frontend_url', env('FRONTEND_URL')), '/').'/documento/'.$this->document->getRawOriginal('public_token'),
            'action_label' => 'Revisar y firmar',
            'kind' => 'patient-document-assigned',
            'document_request_id' => $this->document->id,
        ];
    }
}
