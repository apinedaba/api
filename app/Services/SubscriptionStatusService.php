<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class SubscriptionStatusService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));
    }

    public function summarize(User $user): array
    {
        $user->loadMissing('subscription');
        $subscription = $user->subscription;

        if ($user->has_lifetime_access) {
            return [
                'status_key' => 'lifetime',
                'status_label' => 'Vitalicio',
                'headline' => 'Tu acceso vitalicio está activo.',
                'description' => 'Tienes acceso completo a MindMeet sin renovaciones ni cobros recurrentes.',
                'can_access' => true,
                'can_manage' => false,
                'show_available_plans' => false,
                'requires_payment_update' => false,
                'subscription' => null,
                'stripe_subscription' => null,
                'has_lifetime_access' => true,
            ];
        }

        if (!$subscription) {
            return [
                'status_key' => 'not_subscribed',
                'status_label' => 'Sin suscripción',
                'headline' => 'Aún no tienes un plan activo.',
                'description' => 'Activa tu suscripción para publicar tu perfil y seguir usando las herramientas de MindMeet.',
                'can_access' => false,
                'can_manage' => false,
                'show_available_plans' => true,
                'requires_payment_update' => false,
                'subscription' => null,
                'stripe_subscription' => null,
                'has_lifetime_access' => false,
            ];
        }

        $remoteSubscription = $this->retrieveStripeSubscription($subscription->stripe_id);
        if ($remoteSubscription) {
            $subscription = $this->syncLocalSubscription($subscription, $remoteSubscription);
        }

        $status = $remoteSubscription?->status ?: ($subscription->stripe_status ?: 'not_subscribed');
        $periodEnd = $this->resolvePeriodEnd($remoteSubscription, $subscription);
        $cancelAt = $this->resolveCancelAt($remoteSubscription, $subscription);
        $cancelAtPeriodEnd = (bool) ($remoteSubscription?->cancel_at_period_end ?? false);
        $uiStatus = $this->resolveUiStatus($status, $cancelAtPeriodEnd, $periodEnd, $subscription);

        return [
            'status_key' => $uiStatus,
            'status_label' => $this->statusLabel($uiStatus),
            'headline' => $this->headlineFor($uiStatus),
            'description' => $this->descriptionFor($uiStatus, $periodEnd),
            'can_access' => in_array($uiStatus, ['active', 'trialing', 'canceling'], true),
            'can_manage' => filled($user->stripe_id),
            'show_available_plans' => in_array($uiStatus, ['not_subscribed', 'canceled', 'trial_disabled', 'incomplete_expired'], true),
            'requires_payment_update' => in_array($uiStatus, ['past_due', 'unpaid', 'incomplete'], true),
            'subscription' => $subscription,
            'stripe_subscription' => $remoteSubscription?->toArray(),
            'has_lifetime_access' => false,
            'trial_ends_at' => optional($subscription->trial_ends_at)?->toIso8601String(),
            'ends_at' => optional($subscription->ends_at)?->toIso8601String(),
            'period_end' => $periodEnd?->toIso8601String(),
            'cancel_at' => $cancelAt?->toIso8601String(),
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'raw_status' => $status,
            'details' => $this->buildSubscriptionDetails($user, $subscription, $remoteSubscription, $uiStatus, $periodEnd),
        ];
    }

    protected function retrieveStripeSubscription(?string $stripeId): mixed
    {
        if (!filled($stripeId)) {
            return null;
        }

        try {
            return \Stripe\Subscription::retrieve($stripeId, [
                'expand' => [
                    'items.data.price.product',
                    'default_payment_method',
                    'latest_invoice.payment_intent.payment_method',
                    'customer',
                ],
            ]);
        } catch (ApiErrorException $exception) {
            Log::warning('No se pudo obtener la suscripción desde Stripe', [
                'stripe_id' => $stripeId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function syncLocalSubscription(Subscription $subscription, mixed $remoteSubscription): Subscription
    {
        $subscription->fill([
            'stripe_plan' => data_get($remoteSubscription, 'items.data.0.price.id')
                ?: data_get($remoteSubscription, 'items.data.0.plan.id')
                ?: $subscription->stripe_plan,
            'stripe_status' => $remoteSubscription->status ?: $subscription->stripe_status,
            'trial_ends_at' => $this->carbonFromTimestamp($remoteSubscription->trial_end),
            'ends_at' => $this->resolveEndsAtForSync($remoteSubscription, $subscription),
        ]);

        if ($subscription->isDirty()) {
            $subscription->save();
        }

        return $subscription->fresh();
    }

    protected function resolveUiStatus(string $status, bool $cancelAtPeriodEnd, ?Carbon $periodEnd, Subscription $subscription): string
    {
        if ($subscription->stripe_status === 'trial_disabled') {
            return 'trial_disabled';
        }

        if ($cancelAtPeriodEnd && $periodEnd && $periodEnd->isFuture()) {
            return 'canceling';
        }

        return match ($status) {
            'trialing' => 'trialing',
            'active' => 'active',
            'past_due' => 'past_due',
            'unpaid' => 'unpaid',
            'paused' => 'paused',
            'incomplete' => 'incomplete',
            'incomplete_expired' => 'incomplete_expired',
            'canceled' => 'canceled',
            'pending' => 'pending',
            default => 'not_subscribed',
        };
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'trialing' => 'Prueba activa',
            'active' => 'Activa',
            'canceling' => 'Cancelada al final del periodo',
            'past_due' => 'Pago pendiente',
            'unpaid' => 'Pago vencido',
            'paused' => 'Pausada',
            'incomplete' => 'Pago incompleto',
            'incomplete_expired' => 'Pago expirado',
            'canceled' => 'Cancelada',
            'pending' => 'Procesando activación',
            'trial_disabled' => 'Prueba finalizada',
            'lifetime' => 'Vitalicio',
            default => 'Sin suscripción',
        };
    }

    protected function headlineFor(string $status): string
    {
        return match ($status) {
            'trialing' => 'Tu prueba está activa.',
            'active' => 'Tu suscripción está activa.',
            'canceling' => 'Tu suscripción seguirá activa hasta el final del periodo.',
            'past_due' => 'No pudimos cobrar tu renovación.',
            'unpaid' => 'Tu suscripción tiene un saldo pendiente.',
            'paused' => 'Tu suscripción está pausada.',
            'incomplete' => 'Tu activación aún no se completa.',
            'incomplete_expired' => 'La activación de tu suscripción expiró.',
            'canceled' => 'Tu suscripción ya no está activa.',
            'pending' => 'Estamos confirmando tu suscripción.',
            'trial_disabled' => 'Tu prueba terminó y tu acceso fue deshabilitado.',
            default => 'Aún no tienes un plan activo.',
        };
    }

    protected function descriptionFor(string $status, ?Carbon $periodEnd): string
    {
        $formattedEnd = $this->formatCarbonLabel($periodEnd);

        return match ($status) {
            'trialing' => $formattedEnd
                ? "Puedes usar MindMeet normalmente. Tu primer cobro está previsto para el {$formattedEnd}."
                : 'Puedes usar MindMeet normalmente mientras termina tu periodo de prueba.',
            'active' => $formattedEnd
                ? "Tu membresía está al corriente. El próximo corte estimado es el {$formattedEnd}."
                : 'Tu membresía está al corriente y tu perfil sigue visible en MindMeet.',
            'canceling' => $formattedEnd
                ? "Ya programaste la cancelación. Mantendrás tu acceso hasta el {$formattedEnd}."
                : 'Ya programaste la cancelación, pero conservas acceso hasta el cierre del periodo actual.',
            'past_due' => 'Actualiza tu método de pago para evitar interrupciones en tu acceso y visibilidad dentro de la plataforma.',
            'unpaid' => 'Tu renovación quedó sin cubrir. Regulariza el cobro para recuperar el servicio sin fricción.',
            'paused' => 'Tu suscripción está pausada temporalmente. Revisa tu cuenta para retomarla.',
            'incomplete' => 'Stripe aún no confirma el pago inicial. Puedes revisar tu método de pago o intentar nuevamente.',
            'incomplete_expired' => 'El intento de activación ya expiró. Necesitas iniciar una nueva suscripción.',
            'canceled' => 'Puedes activar un nuevo plan cuando quieras para retomar tu acceso completo.',
            'pending' => 'Estamos esperando la confirmación final de Stripe. Esta pantalla se actualizará en cuanto quede lista.',
            'trial_disabled' => 'Activa un plan para recuperar tu acceso completo, tu agenda y la visibilidad de tu perfil.',
            default => 'Elige un plan para seguir usando las herramientas y beneficios de MindMeet.',
        };
    }

    protected function resolveEndsAtForSync(mixed $remoteSubscription, Subscription $subscription): ?Carbon
    {
        if ($remoteSubscription->status === 'canceled' && $remoteSubscription->ended_at) {
            return $this->carbonFromTimestamp($remoteSubscription->ended_at);
        }

        if ($remoteSubscription->cancel_at_period_end && $remoteSubscription->current_period_end) {
            return $this->carbonFromTimestamp($remoteSubscription->current_period_end);
        }

        if ($remoteSubscription->cancel_at) {
            return $this->carbonFromTimestamp($remoteSubscription->cancel_at);
        }

        return $subscription->ends_at;
    }

    protected function resolvePeriodEnd(mixed $remoteSubscription, Subscription $subscription): ?Carbon
    {
        if ($remoteSubscription?->current_period_end) {
            return $this->carbonFromTimestamp($remoteSubscription->current_period_end);
        }

        return $subscription->ends_at;
    }

    protected function resolveCancelAt(mixed $remoteSubscription, Subscription $subscription): ?Carbon
    {
        if ($remoteSubscription?->cancel_at) {
            return $this->carbonFromTimestamp($remoteSubscription->cancel_at);
        }

        return $subscription->ends_at;
    }

    protected function carbonFromTimestamp(?int $timestamp): ?Carbon
    {
        return $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }

    protected function buildSubscriptionDetails(
        User $user,
        Subscription $subscription,
        mixed $remoteSubscription,
        string $uiStatus,
        ?Carbon $periodEnd
    ): array {
        $price = data_get($remoteSubscription, 'items.data.0.price');
        $product = data_get($price, 'product');
        $paymentMethod = $this->resolvePaymentMethod($remoteSubscription);
        $startedAt = $this->resolveStartedAt($remoteSubscription, $subscription);
        $nextPaymentDate = $this->resolveNextPaymentDate($uiStatus, $remoteSubscription, $subscription, $periodEnd);
        $amount = $this->resolveAmount($price);
        $currency = strtoupper((string) ($price->currency ?? 'MXN'));
        $interval = data_get($price, 'recurring.interval');
        $intervalCount = (int) data_get($price, 'recurring.interval_count', 1);
        $planMetadata = $this->safeJsonDecode(data_get($price, 'metadata.data', data_get($product, 'metadata.data')));
        $planTitle = data_get($planMetadata, 'title')
            ?: data_get($product, 'name')
            ?: ($uiStatus === 'trialing' ? 'Prueba MindMeet' : 'Plan MindMeet');

        return [
            'plan_title' => $planTitle,
            'plan_subtitle' => data_get($planMetadata, 'subtitle'),
            'started_at' => $startedAt?->toIso8601String(),
            'started_at_label' => $this->formatCarbonLabel($startedAt),
            'next_payment_at' => $nextPaymentDate?->toIso8601String(),
            'next_payment_at_label' => $this->formatCarbonLabel($nextPaymentDate),
            'amount' => $amount,
            'amount_decimal' => $amount !== null ? $amount / 100 : null,
            'currency' => $currency,
            'recurrence' => $this->formatRecurrenceLabel($interval, $intervalCount),
            'recurrence_interval' => $interval,
            'recurrence_count' => $intervalCount,
            'payment_method' => $paymentMethod,
            'trial_ends_at_label' => $this->formatCarbonLabel($subscription->trial_ends_at),
            'raw_price_id' => data_get($price, 'id'),
        ];
    }

    protected function resolveStartedAt(mixed $remoteSubscription, Subscription $subscription): ?Carbon
    {
        if ($remoteSubscription?->start_date) {
            return $this->carbonFromTimestamp($remoteSubscription->start_date);
        }

        return $subscription->created_at;
    }

    protected function resolveNextPaymentDate(
        string $uiStatus,
        mixed $remoteSubscription,
        Subscription $subscription,
        ?Carbon $periodEnd
    ): ?Carbon {
        if ($uiStatus === 'trialing' && $subscription->trial_ends_at) {
            return $subscription->trial_ends_at instanceof Carbon
                ? $subscription->trial_ends_at
                : Carbon::parse($subscription->trial_ends_at);
        }

        if ($periodEnd) {
            return $periodEnd;
        }

        if ($remoteSubscription?->current_period_end) {
            return $this->carbonFromTimestamp($remoteSubscription->current_period_end);
        }

        return null;
    }

    protected function resolveAmount(mixed $price): ?int
    {
        if (!$price) {
            return null;
        }

        return data_get($price, 'unit_amount');
    }

    protected function resolvePaymentMethod(mixed $remoteSubscription): ?string
    {
        $method = data_get($remoteSubscription, 'default_payment_method')
            ?: data_get($remoteSubscription, 'latest_invoice.payment_intent.payment_method')
            ?: data_get($remoteSubscription, 'customer.invoice_settings.default_payment_method');

        if (!$method) {
            return null;
        }

        $type = $method->type ?? null;
        if ($type === 'card') {
            $brand = strtoupper((string) data_get($method, 'card.brand'));
            $last4 = data_get($method, 'card.last4');
            return trim("Tarjeta {$brand} terminacion {$last4}");
        }

        if ($type === 'oxxo') {
            return 'OXXO';
        }

        return $type ? strtoupper((string) $type) : null;
    }

    protected function formatRecurrenceLabel(?string $interval, int $intervalCount): ?string
    {
        if (!$interval) {
            return null;
        }

        $base = match ($interval) {
            'day' => 'diaria',
            'week' => 'semanal',
            'month' => 'mensual',
            'year' => 'anual',
            default => $interval,
        };

        if ($intervalCount <= 1) {
            return $base;
        }

        return "cada {$intervalCount} {$base}";
    }

    protected function safeJsonDecode(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function formatCarbonLabel(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        $carbon = $value instanceof Carbon ? $value : Carbon::parse($value);

        return $carbon->locale('es_MX')->translatedFormat('d \\d\\e F \\d\\e Y');
    }
}
