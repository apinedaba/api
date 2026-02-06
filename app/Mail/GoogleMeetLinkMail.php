<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GoogleMeetLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public $patientName;
    public $meetLink;
    public $fecha;
    public $hora;
    public $isUpdate;
    public $linkChanged;

    /**
     * Create a new message instance.
     */
    public function __construct($patientName, $meetLink, $fecha, $hora, $isUpdate = false, $linkChanged = false)
    {
        $this->patientName = $patientName;
        $this->meetLink = $meetLink;
        $this->fecha = $fecha;
        $this->hora = $hora;
        $this->isUpdate = $isUpdate;
        $this->linkChanged = $linkChanged;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->isUpdate ? 'Actualización de tu sesión en MindMeet' : 'Enlace de tu sesión en MindMeet';

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'email.googleMeetLink',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
