<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Webhook;
use App\Jobs\HandleStripeWebhookJob;

class StripeWebhookController extends Controller
{

    public function handle(Request $request)
    {

        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');

        $event = Webhook::constructEvent(
            $payload,
            $sig,
            config('services.stripe.webhook_secret')
        );

        HandleStripeWebhookJob::dispatch($event);

        return response()->json(['received' => true]);

    }

}