<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class SubscriptionController extends Controller
{

    public function checkout(Request $request)
    {

        Stripe::setApiKey(config('services.stripe.secret_key'));

        $user = $request->user();

        if (!$user->stripe_id) {

            $customer = \Stripe\Customer::create([
                'email' => $user->email,
                'name' => $user->name
            ]);

            $user->update([
                'stripe_id' => $customer->id
            ]);

        }

        $session = Session::create([

            'mode' => 'subscription',

            'customer' => $user->stripe_id,

            'line_items' => [
                [
                    'price' => config('services.stripe.plan_minder'),
                    'quantity' => 1
                ]
            ],
            'subscription_data' => [
                'trial_period_days' => 15
            ],

            'success_url' => config('app.front_url') . '/perfil/suscripcion?status=success',

            'cancel_url' => config('app.front_url') . '/perfil/suscripcion?status=cancel',

            'metadata' => [
                'user_id' => $user->id
            ],

            'locale' => 'es-419'

        ]);

        return response()->json([
            'url' => $session->url
        ]);

    }

}