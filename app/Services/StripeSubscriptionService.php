<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Stripe\Stripe;

class StripeSubscriptionService
{
    public function __construct()
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

        Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'stripe_id' => $subscription->id,
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
    }

    public function updateSubscription($subscription): void
    {
        Subscription::where('stripe_id', $subscription->id)
            ->update([
                'stripe_plan' => $subscription->items->data[0]->price->id ?? null,
                'stripe_status' => $subscription->status,
                'trial_ends_at' => $subscription->trial_end
                    ? Carbon::createFromTimestamp($subscription->trial_end)
                    : null,
                'ends_at' => $this->resolveEndsAt($subscription),
            ]);
    }

    public function cancelSubscription($subscription): void
    {
        Subscription::where('stripe_id', $subscription->id)
            ->update([
                'ends_at' => now(),
                'stripe_status' => 'canceled',
            ]);
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
}
