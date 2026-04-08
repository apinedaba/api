<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
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

    public function __construct($patientName, $meetLink, $fecha, $hora, $isUpdate = false, $linkChanged = false)
    {
        $this->patientName = $patientName;
        $this->meetLink = $meetLink;
        $this->fecha = $fecha;
        $this->hora = $hora;
        $this->isUpdate = $isUpdate;
        $this->linkChanged = $linkChanged;
    }

    public function envelope(): Envelope
    {
        $subject = $this->isUpdate
            ? "MindMeet | Actualizacion del enlace de tu sesion para {$this->fecha} a las {$this->hora}"
            : "MindMeet | Enlace de tu sesion para {$this->fecha} a las {$this->hora}";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.googleMeetLink',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
