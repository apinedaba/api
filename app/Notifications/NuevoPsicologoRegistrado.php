<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class NuevoPsicologoRegistrado extends Notification
{
    use Queueable;

    protected $user;

    /**
     * Create a new notification instance.
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // ✅ Genera el link firmado de verificación
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // ✅ Forzar host minder.mindmeet.mx
        $parsed = parse_url(config('app.url'), PHP_URL_HOST);
        $verificationUrl = str_replace($parsed, 'minder.mindmeet.mx', $verificationUrl);


        $environtment = env('APP_ENV', 'local');

        if ($environtment === 'production') {
            # code...
            // 📩 Correo al usuario
            // 📩 Enviar copia interna
            $this->enviarNotificacionInterna($this->user);
            return (new MailMessage)
                ->subject('Tu código de verificación de MindMeet')
                ->view('email.registro', [
                    'usuario' => $this->user
                ]);
        } else {
            // 📩 Correo al usuario en desarrollo
            Log::info('Enviando correo de verificación en entorno de desarrollo');
            Log::info('URL de verificación: ' . $verificationUrl);
            return (new MailMessage)
                ->subject('Tu código de verificación de MindMeet')
                ->view('email.registro', [
                    'usuario' => $this->user
                ]);
        }
    }

    protected function enviarNotificacionInterna($user)
    {
        $correosInternos = ['jhernandez961116@gmail.com', 'apinedabawork@gmail.com', 'axelboyzowork@gmail.com'];

        foreach ($correosInternos as $correo) {
            Mail::raw("Nuevo registro:\nNombre: {$user->name}\nCorreo: {$user->email}", function ($message) use ($correo) {
                $message->to($correo)
                    ->subject('Nuevo usuario registrado en MindMeet');
            });
        }
    }
}
