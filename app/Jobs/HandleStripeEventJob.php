<?php

namespace App\Jobs;

use App\Events\SubscriptionActivated;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionBillingNotificationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleStripeEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $event;

    public function __construct($event)
    {
        $this->event = $event;
    }

    public function handle(): void
    {
        Log::info("Stripe event received: {$this->event->type}");

        switch ($this->event->type) {
            case 'checkout.session.completed':
                $session = $this->event->data->object;
                Log::info('Checkout session completed');

                // ── Suscripciones (lógica original, intacta) ─────────────────
                if (($session->mode ?? null) === 'subscription') {
                    $this->handleNewSubscription($session);
                }

                // ── MindBoost: pago de campaña de marketing ───────────────────
                // El servicio es idempotente; si ya fue procesado por el otro
                // webhook (/stripe/webhook), retorna silenciosamente.
                if (! empty(data_get($session, 'metadata.campaign_request_id'))) {
                    app(\App\Services\MarketingPaymentService::class)
                        ->handleCheckoutCompleted($session);
                }

                break;

            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $this->handleSubscriptionChange($this->event->data->object);
                break;

            case 'invoice.payment_failed':
                $this->handleFailedPayment($this->event->data->object);
                break;
        }
    }

    protected function handleNewSubscription($session): void
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret_key'));

        $user = User::find($session->metadata->user_id ?? null);

        if (!$user) {
            return;
        }

        $stripeSubscription = \Stripe\Subscription::retrieve($session->subscription);

        Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'stripe_id' => $stripeSubscription->id,
                'stripe_plan' => $stripeSubscription->items->data[0]->price->id,
                'stripe_status' => $stripeSubscription->status,
                'trial_ends_at' => $stripeSubscription->trial_end
                    ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
                    : null,
                'ends_at' => null,
            ]
        );

        Subscription::where('user_id', $user->id)
            ->where('stripe_id', '!=', $stripeSubscription->id)
            ->update([
                'stripe_status' => 'canceled',
                'ends_at' => now(),
            ]);

        broadcast(new SubscriptionActivated($user->id));

        Log::info("Nueva suscripcion creada para el usuario: {$user->id}");
    }

    protected function handleSubscriptionChange($stripeSubscription): void
    {
        $subscription = Subscription::where('stripe_id', $stripeSubscription->id)->first();

        if (!$subscription) {
            return;
        }

        $subscription->update([
            'stripe_plan' => $stripeSubscription->items->data[0]->price->id,
            'stripe_status' => $stripeSubscription->status,
            'trial_ends_at' => $stripeSubscription->trial_end
                ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
                : null,
            'ends_at' => $this->resolveSubscriptionEndsAt($stripeSubscription),
        ]);

        Log::info("Suscripcion actualizada: {$stripeSubscription->id} a estado {$stripeSubscription->status}");
    }

    protected function handleFailedPayment($invoice): void
    {
        $customerId = $invoice->customer ?? null;

        Log::warning('Stripe invoice payment failed', [
            'invoice_id' => $invoice->id ?? null,
            'customer' => $customerId,
            'subscription' => $invoice->subscription ?? null,
        ]);

        if (!$customerId) {
            Log::error("Invoice {$invoice->id} sin customer de Stripe");
            return;
        }

        $user = User::where('stripe_id', $customerId)->first();

        if (!$user) {
            Log::error("Usuario no encontrado para customer {$customerId}");
            return;
        }

        if (!empty($invoice->subscription)) {
            Subscription::where('stripe_id', $invoice->subscription)
                ->update(['stripe_status' => 'past_due']);
        }

        app(SubscriptionBillingNotificationService::class)->notifyFailedCharge($user, $invoice);
    }

    protected function resolveSubscriptionEndsAt($stripeSubscription): ?Carbon
    {
        if (!empty($stripeSubscription->ended_at)) {
            return Carbon::createFromTimestamp($stripeSubscription->ended_at);
        }

        if (!empty($stripeSubscription->cancel_at_period_end) && !empty($stripeSubscription->current_period_end)) {
            return Carbon::createFromTimestamp($stripeSubscription->current_period_end);
        }

        if (!empty($stripeSubscription->cancel_at)) {
            return Carbon::createFromTimestamp($stripeSubscription->cancel_at);
        }

        return null;
    }
}
