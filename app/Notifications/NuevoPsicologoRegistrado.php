<?php

namespace App\Notifications;

use App\Notifications\Traits\NotificaInternamente;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class NuevoPsicologoRegistrado extends Notification
{
    use Queueable, NotificaInternamente;

    protected $user;
    protected $esRegistro;

    public function __construct($user, $esRegistro = true)
    {
        $this->user = $user;
        $this->esRegistro = $esRegistro;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($notifiable->hasVerifiedEmail()) {
            return (new MailMessage)
                ->subject('Te damos la bienvenida a MindMeet')
                ->view('email.professional-welcome-active', [
                    'name' => $notifiable->name ?? '',
                    'dashboardUrl' => rtrim(config('app.front_url'), '/') . '/dashboard',
                ]);
        }

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        $parsed = parse_url(config('app.url'), PHP_URL_HOST);
        $verificationUrl = str_replace($parsed, 'minder.mindmeet.com.mx', $verificationUrl);

        if ($this->esRegistro) {
            $asunto = 'Nuevo psicologo registrado en MindMeet';
            $cuerpo = "Nuevo registro de psicologo:\nNombre: {$this->user->name}\nCorreo: {$this->user->email}";
            $this->enviarNotificacionInterna($this->user, $asunto, $cuerpo);
        }

        return (new MailMessage)
            ->subject('Te damos la bienvenida a MindMeet')
            ->view('email.registro', [
                'usuario' => $this->user,
                'verificationUrl' => $verificationUrl,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Bienvenido a MindMeet',
            'body' => $notifiable->hasVerifiedEmail()
                ? 'Tu cuenta profesional ya esta activa y lista para usarse.'
                : 'Tu cuenta fue creada. Verifica tu correo para terminar la activacion.',
            'action_url' => rtrim(config('app.front_url'), '/') . '/dashboard',
            'action_label' => 'Abrir dashboard',
            'kind' => 'welcome',
        ];
    }
}
