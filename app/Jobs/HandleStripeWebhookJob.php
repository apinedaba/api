<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HandleStripeWebhookJob implements ShouldQueue
{

    public $event;

    public function __construct($event)
    {
        $this->event = $event;
    }

    public function handle()
    {

        switch ($this->event->type) {

            case 'checkout.session.completed':

                if ($this->event->data->object->mode === 'subscription') {

                    app(\App\Services\StripeSubscriptionService::class)
                        ->handleNewSubscription($this->event->data->object);

                }

                break;

            case 'customer.subscription.updated':

                app(\App\Services\StripeSubscriptionService::class)
                    ->updateSubscription($this->event->data->object);

                break;

            case 'customer.subscription.deleted':

                app(\App\Services\StripeSubscriptionService::class)
                    ->cancelSubscription($this->event->data->object);

                break;

            case 'invoice.payment_failed':

                app(\App\Services\StripeSubscriptionService::class)
                    ->paymentFailed($this->event->data->object);

                break;

        }

    }

}