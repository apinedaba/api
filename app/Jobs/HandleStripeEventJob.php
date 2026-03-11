<?php

namespace App\Jobs;

use App\Events\NewNotification;
use App\Events\SubscriptionActivated;
use App\Models\User;
use App\Models\Subscription;
use App\Services\EmailService;
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

    public function handle()
    {
        Log::info("Stripe event received: {$this->event->type}");
        switch ($this->event->type) {

            case 'checkout.session.completed':
                $session = $this->event->data->object;
                Log::info("Checkout session completed");
                if ($session->mode == 'subscription') {
                    $this->handleNewSubscription($session);

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
    protected function handleNewSubscription($session)
    {
        logger(config('services.stripe.secret_key'));
        \Stripe\Stripe::setApiKey(config('services.stripe.secret_key'));

        $user = User::find($session->metadata->user_id);
        if ($user) {
            $stripeSubscription = \Stripe\Subscription::retrieve($session->subscription);
            Subscription::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'stripe_id' => $stripeSubscription->id,
                    'stripe_plan' => $stripeSubscription->items->data[0]->price->id,
                    'stripe_status' => $stripeSubscription->status,
                    'trial_ends_at' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                    'ends_at' => null,
                ]
            );
            logger("JOB EJECUTADO");
            broadcast(new SubscriptionActivated($user->id));
            logger("EVENTO DISPARADO");
            Log::info("Nueva suscripción creada para el usuario: {$user->id}");
        }

    }

    protected function handleSubscriptionChange($stripeSubscription)
    {
        $subscription = Subscription::where('stripe_id', $stripeSubscription->id)->first();
        if ($subscription) {
            $subscription->update([
                'stripe_plan' => $stripeSubscription->items->data[0]->price->id,
                'stripe_status' => $stripeSubscription->status,
                'trial_ends_at' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                'ends_at' => $stripeSubscription->cancel_at ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->cancel_at) : null,
            ]);
            Log::info("Suscripción actualizada: {$stripeSubscription->id} a estado {$stripeSubscription->status}");
        }
    }
    protected function handleFailedPayment($invoice)
    {
        // 🔒 Validación defensiva
        $userId = $invoice->customer ?? null;
        Log::info("{$invoice}");
        if (!$userId) {
            Log::error("Invoice {$invoice->id} sin user_id");
            return;
        }

        $user = User::where('stripe_id', $userId)->first();

        if (!$user) {
            Log::error("Usuario no encontrado: {$userId}");
            return;
        }

        // 📧 Enviar correo (cola)
        EmailService::send(
            $user->email,
            'Tu intento de pago no pudo completarse – MindMeet',
            'emails.payment-failed',
            [
                'name' => $user->name
            ]
        );

        // 🔄 Actualizar suscripción
        if (!empty($invoice->subscription)) {
            Subscription::where('stripe_id', $invoice->subscription)
                ->update(['stripe_status' => 'past_due']);

            Log::warning("Fallo en pago de suscripción: {$invoice->subscription}");
        }
    }
}
