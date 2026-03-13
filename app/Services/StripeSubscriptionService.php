<?php
namespace App\Services;

use App\Models\User;
use App\Models\Subscription;
use Stripe\Stripe;

class StripeSubscriptionService
{

    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));
    }

    public function handleNewSubscription($session)
    {

        $user = User::find($session->metadata->user_id);

        if (!$user) {
            return;
        }

        $subscription = \Stripe\Subscription::retrieve($session->subscription);

        Subscription::updateOrCreate(

            ['stripe_id' => $subscription->id],

            [
                'user_id' => $user->id,
                'stripe_plan' => $subscription->items->data[0]->price->id,
                'stripe_status' => $subscription->status,
                'trial_ends_at' => $subscription->trial_end
                    ? \Carbon\Carbon::createFromTimestamp($subscription->trial_end)
                    : null,
                'ends_at' => null
            ]

        );

    }

    public function updateSubscription($subscription)
    {

        Subscription::where('stripe_id', $subscription->id)
            ->update([
                'stripe_status' => $subscription->status
            ]);

    }

    public function cancelSubscription($subscription)
    {

        Subscription::where('stripe_id', $subscription->id)
            ->update([
                'ends_at' => now(),
                'stripe_status' => 'canceled'
            ]);

    }

    public function paymentFailed($invoice)
    {

        Subscription::where('stripe_id', $invoice->subscription)
            ->update([
                'stripe_status' => 'past_due'
            ]);

    }

}