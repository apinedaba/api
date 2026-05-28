<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionBillingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $kind,
        protected array $context = []
    ) {
    }

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->title();
        $body = $this->body();

        $mail = (new MailMessage)
            ->subject("MindMeet | {$title}")
            ->greeting('Hola ' . ($notifiable->name ?? ''))
            ->line($body);

        if ($detail = $this->detailLine()) {
            $mail->line($detail);
        }

        if ($secondary = $this->secondaryLine()) {
            $mail->line($secondary);
        }

        return $mail->action($this->actionLabel(), $this->actionUrl());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'action_url' => $this->actionUrl(),
            'action_label' => $this->actionLabel(),
            'kind' => $this->kind,
            'next_charge_at' => $this->context['next_charge_at'] ?? null,
            'next_retry_at' => $this->context['next_retry_at'] ?? null,
            'amount' => $this->context['amount'] ?? null,
            'currency' => $this->context['currency'] ?? 'MXN',
        ];
    }

    protected function title(): string
    {
        return match ($this->kind) {
            'subscription-upcoming-charge' => 'Tu cobro de MindMeet se acerca',
            'subscription-charge-failed' => 'No pudimos procesar tu cobro',
            default => 'Actualizacion de suscripcion',
        };
    }

    protected function body(): string
    {
        return match ($this->kind) {
            'subscription-upcoming-charge' => sprintf(
                'Tu renovacion de MindMeet esta programada para el %s. Procura contar con fondos suficientes para que el cobro se procese sin interrupciones.',
                $this->formatDate($this->context['next_charge_at'] ?? null)
            ),
            'subscription-charge-failed' => 'No pudimos realizar el cobro de tu suscripcion. Haremos un nuevo intento automaticamente y te recomendamos verificar que tu metodo de pago tenga fondos suficientes.',
            default => 'Tenemos una actualizacion importante sobre tu suscripcion.',
        };
    }

    protected function detailLine(): ?string
    {
        $amount = $this->context['amount'] ?? null;
        $currency = strtoupper((string) ($this->context['currency'] ?? 'MXN'));

        if ($amount === null) {
            return null;
        }

        return 'Monto estimado: ' . $this->formatAmount($amount) . " {$currency}.";
    }

    protected function secondaryLine(): ?string
    {
        return match ($this->kind) {
            'subscription-upcoming-charge' => 'Si necesitas actualizar tu metodo de pago o revisar tu plan, puedes hacerlo desde tu panel de suscripcion.',
            'subscription-charge-failed' => ($retryDate = $this->formatRetryDate($this->context['next_retry_at'] ?? null))
                ? "Stripe intentara nuevamente el cargo alrededor del {$retryDate}."
                : 'Puedes actualizar tu metodo de pago desde tu panel de suscripcion para evitar pausas en tu cuenta.',
            default => null,
        };
    }

    protected function actionLabel(): string
    {
        return match ($this->kind) {
            'subscription-upcoming-charge' => 'Revisar suscripcion',
            'subscription-charge-failed' => 'Actualizar metodo de pago',
            default => 'Abrir suscripcion',
        };
    }

    protected function actionUrl(): string
    {
        return rtrim(config('app.front_url_psicologo') ?: config('app.front_url'), '/') . '/perfil/suscripcion';
    }

    protected function formatDate(?string $value): string
    {
        if (!$value) {
            return 'proximamente';
        }

        return Carbon::parse($value)
            ->timezone(config('app.timezone'))
            ->locale('es_MX')
            ->translatedFormat('d \\d\\e F \\d\\e Y');
    }

    protected function formatRetryDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        return Carbon::parse($value)
            ->timezone(config('app.timezone'))
            ->locale('es_MX')
            ->translatedFormat('d \\d\\e F \\d\\e Y \\a \\l\\a\\s H:i');
    }

    protected function formatAmount(mixed $amount): string
    {
        if (!is_numeric($amount)) {
            return '$0.00';
        }

        $numeric = (float) $amount;
        if ($numeric > 9999) {
            $numeric = $numeric / 100;
        }

        return '$' . number_format($numeric, 2);
    }
}
