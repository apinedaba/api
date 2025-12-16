<?php

namespace App\Services;

use App\Mail\BaseMail;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    public static function send(
        string|array $to,
        string $subject,
        string $template,
        array $params = []
    ): void {
        \Log::info("Enviando correo a {$to}");
        Mail::to($to)->send(
            new BaseMail($subject, $template, $params)
        );
    }
}
