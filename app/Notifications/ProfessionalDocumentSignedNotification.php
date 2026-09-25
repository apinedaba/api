<?php

namespace App\Notifications;

use App\Models\Patient;
use App\Models\PatientDocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProfessionalDocumentSignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected PatientDocumentRequest $document,
        protected Patient $patient
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Documento firmado',
            'body' => ($this->document->signer_name ?: $this->patient->name).' firmó “'.$this->document->title.'”.',
            'action_url' => rtrim(config('app.front_url_psicologo') ?: config('app.front_url_user') ?: config('app.front_url'), '/').'/pacientes/'.$this->patient->id.'?tab=15',
            'action_label' => 'Ver documento',
            'kind' => 'patient-document-signed',
            'patient_id' => $this->patient->id,
            'document_request_id' => $this->document->id,
            'document' => [
                'id' => $this->document->id,
                'patient_id' => $this->patient->id,
                'title' => $this->document->title,
                'status' => $this->document->status,
                'signer_name' => $this->document->signer_name,
                'signed_at' => $this->document->signed_at?->toISOString(),
                'public_url' => $this->document->public_url,
                'pdf_url' => $this->document->pdf_url,
                'requires_signature' => (bool) $this->document->requires_signature,
            ],
        ];
    }
}
