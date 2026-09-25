<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Stripe\Stripe;

class StripeSubscriptionService
{
    public function __construct(
        private PlanCatalogService $planCatalog,
        private PlanAssignmentService $planAssignment
    )
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));
    }

    public function handleNewSubscription($session): void
    {
        $user = User::find($session->metadata->user_id ?? null);

        if (!$user) {
            return;
        }

        $subscription = \Stripe\Subscription::retrieve($session->subscription);
        $plan = $this->planCatalog->fromStripePrice(data_get($subscription, 'items.data.0.price'));

        Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'stripe_id' => $subscription->id,
                'plan_id' => $plan?->id,
                'stripe_plan' => $subscription->items->data[0]->price->id,
                'stripe_status' => $subscription->status,
                'trial_ends_at' => $subscription->trial_end
                    ? Carbon::createFromTimestamp($subscription->trial_end)
                    : null,
                'ends_at' => null,
            ]
        );

        Subscription::where('user_id', $user->id)
            ->where('stripe_id', '!=', $subscription->id)
            ->update([
                'stripe_status' => 'canceled',
                'ends_at' => now(),
            ]);

        if ($subscription->status === 'active') {
            if ($plan) $user->update(['plan_id' => $plan->id]);
            $this->notifySellerPayment($user->id);
        }
    }

    public function updateSubscription($subscription): void
    {
        $localSubscription = Subscription::where('stripe_id', $subscription->id)->first();
        $user = $localSubscription?->user
            ?: User::where('stripe_id', $subscription->customer ?? null)->first();
        if (!$user) {
            Log::warning('No se encontró usuario para sincronizar suscripción de Stripe.', ['stripe_subscription_id' => $subscription->id]);
            return;
        }
        $plan = $this->planCatalog->fromStripePrice(data_get($subscription, 'items.data.0.price'));
        $localSubscription = Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'stripe_id' => $subscription->id,
                'stripe_plan' => $subscription->items->data[0]->price->id ?? null,
                'plan_id' => $plan?->id,
                'stripe_status' => $subscription->status,
                'trial_ends_at' => $subscription->trial_end
                    ? Carbon::createFromTimestamp($subscription->trial_end)
                    : null,
                'ends_at' => $this->resolveEndsAt($subscription),
            ]);

        if ($subscription->status === 'active') {
            if ($plan) $user->update(['plan_id' => $plan->id]);
            $this->notifySellerPayment($user->id);
        }
    }

    public function cancelSubscription($subscription): void
    {
        $local = Subscription::where('stripe_id', $subscription->id)->first();
        if ($local) {
            $local->update([
                'ends_at' => now(),
                'stripe_status' => 'canceled',
            ]);
            if ($user = User::find($local->user_id)) $this->planAssignment->activateFree($user);
        }
    }

    public function paymentFailed($invoice): void
    {
        $user = User::where('stripe_id', $invoice->customer ?? null)->first();

        if ($user) {
            app(SubscriptionBillingNotificationService::class)->notifyFailedCharge($user, $invoice);
        }

        Subscription::where('stripe_id', $invoice->subscription)
            ->update([
                'stripe_status' => 'past_due',
            ]);
    }

    protected function resolveEndsAt($subscription): ?Carbon
    {
        if (!empty($subscription->ended_at)) {
            return Carbon::createFromTimestamp($subscription->ended_at);
        }

        if (!empty($subscription->cancel_at_period_end) && !empty($subscription->current_period_end)) {
            return Carbon::createFromTimestamp($subscription->current_period_end);
        }

        if (!empty($subscription->cancel_at)) {
            return Carbon::createFromTimestamp($subscription->cancel_at);
        }

        return null;
    }

    private function notifyRecoveryPayment(int $userId): void
    {
        try {
            app(AdminVendedoresClient::class)->confirmRecoveryPayment($userId);
        } catch (\RuntimeException $exception) {
            // It is valid for paid users not to belong to a recovery portfolio.
            if ($exception->getCode() !== 409) {
                Log::warning('No se pudo acreditar recuperación al CRM de vendedores.', ['user_id' => $userId, 'message' => $exception->getMessage()]);
            }
        }
    }

    private function notifySellerPayment(int $userId): void
    {
        $this->notifyRecoveryPayment($userId);

        try {
            app(AdminVendedoresClient::class)->confirmVendorReferralPayment($userId);
        } catch (\RuntimeException $exception) {
            // Usuarios sin vendedor son esperados; no se convierten en error de Stripe.
            if ($exception->getCode() !== 409) {
                Log::warning('No se pudo confirmar venta atribuida en el CRM de vendedores.', ['user_id' => $userId, 'message' => $exception->getMessage()]);
            }
        }
    }
}
