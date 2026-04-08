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
use App\Notifications\Traits\NotificaInternamente;

class NuevoPsicologoRegistrado extends Notification
{
    use Queueable, NotificaInternamente;

    protected $user;

    /**
     * Create a new notification instance.
     */
    protected $esRegistro;

    /**
     * Create a new notification instance.
     * ✅ El nuevo parámetro $esRegistro será `true` por defecto.
     */
    public function __construct($user, $esRegistro = true)
    {
        $this->user = $user;
        $this->esRegistro = $esRegistro;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {

        if ($notifiable->hasVerifiedEmail()) {
            return (new MailMessage)
                ->subject('¡Te damos la bienvenida a MindMeet!')
                ->greeting("¡Hola, {$notifiable->name}!")
                ->line('Gracias por registrarte en MindMeet a través de tu cuenta de Google. Tu cuenta ya está activa y lista para usar.')
                ->line('¡Ya puedes empezar a explorar la plataforma!')
                ->action('Ir a mi panel', url(config('app.front_url')));
        }

        // ✅ Genera el link firmado de verificación
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // ✅ Forzar host minder.mindmeet.com.mx
        $parsed = parse_url(config('app.url'), PHP_URL_HOST);
        $verificationUrl = str_replace($parsed, 'minder.mindmeet.com.mx', $verificationUrl);

        // 📩 Enviar copia interna
        // 3. ✅ Llama al método del Trait
        if ($this->esRegistro) {
            $asunto = 'Nuevo psicólogo registrado en MindMeet';
            $cuerpo = "Nuevo registro de psicólogo:\nNombre: {$this->user->name}\nCorreo: {$this->user->email}";
            $this->enviarNotificacionInterna($this->user, $asunto, $cuerpo);
        }
        # code...
        // 📩 Correo al usuario
        return (new MailMessage)
            ->subject('¡Te damos la bienvenida a MindMeet!')
            ->view('email.registro', [
                'usuario' => $this->user,
                'verificationUrl' => $verificationUrl
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
