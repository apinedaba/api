<?php
// app/Mail/SendPasswordResetCode.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendPasswordResetCode extends Mailable
{
    use Queueable, SerializesModels;

    public $code;
    public $userName;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($code, $userName)
    {
        $this->code = $code;
        $this->userName = $userName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Código para restablecer tu contraseña en MindMeet')
            ->view('email.passwordReset');
    }
}
