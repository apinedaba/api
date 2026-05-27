<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionPaymentRegisteredNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Appointment $appointment,
        protected Payment $payment
    ) {
    }

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $patient = $this->appointment->patient()->first();
        $start = Carbon::parse($this->appointment->start)->timezone(config('app.timezone'));
        $amount = $this->formatMoney($this->payment->amount);
        $concept = $this->payment->concepto === 'session_deposit'
            ? 'anticipo de sesion'
            : 'pago de sesion';

        return (new MailMessage)
            ->subject('MindMeet | Pago registrado para una sesion')
            ->greeting('Hola ' . ($notifiable->name ?? ''))
            ->line("Se registro un {$concept} por {$amount}.")
            ->line('Paciente: ' . ($patient?->name ?: 'Paciente MindMeet'))
            ->line('Fecha: ' . $start->translatedFormat('d \\d\\e F \\d\\e Y'))
            ->line('Hora: ' . $start->format('H:i'))
            ->line('La cita ya esta registrada en tu agenda.')
            ->action('Ver agenda', $this->professionalAgendaUrl());
    }

    public function toArray(object $notifiable): array
    {
        $patient = $this->appointment->patient()->first();
        $start = Carbon::parse($this->appointment->start)->timezone(config('app.timezone'));
        $amount = $this->formatMoney($this->payment->amount);
        $concept = $this->payment->concepto === 'session_deposit'
            ? 'anticipo'
            : 'pago';

        return [
            'title' => 'Pago registrado',
            'body' => "Se registro un {$concept} de {$amount} para la sesion con " . ($patient?->name ?: 'tu paciente') . " del {$start->format('d/m/Y')} a las {$start->format('H:i')}.",
            'action_url' => $this->professionalAgendaUrl(),
            'action_label' => 'Abrir agenda',
            'kind' => 'session-payment-registered',
            'appointment_id' => $this->appointment->id,
            'payment_id' => $this->payment->id,
            'amount' => (float) $this->payment->amount,
            'currency' => $this->payment->currency,
        ];
    }

    protected function professionalAgendaUrl(): string
    {
        return rtrim(config('app.front_url_psicologo') ?: config('app.front_url_user') ?: config('app.front_url'), '/') . '/agenda';
    }

    protected function formatMoney(mixed $amount): string
    {
        return '$' . number_format((float) $amount, 2) . ' MXN';
    }
}
