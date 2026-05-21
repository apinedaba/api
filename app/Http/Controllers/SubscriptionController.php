<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{

    public function checkout(Request $request)
    {

        Stripe::setApiKey(config('services.stripe.secret_key'));
        $frontendUrl = $this->resolvePsychologistFrontendUrl();

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

            'success_url' => $frontendUrl . '/perfil/suscripcion?status=success',

            'cancel_url' => $frontendUrl . '/perfil/suscripcion?status=cancel',

            'metadata' => [
                'user_id' => $user->id
            ],

            'locale' => 'es-419'

        ]);

        return response()->json([
            'url' => $session->url
        ]);

    }

    protected function resolvePsychologistFrontendUrl(): string
    {
        $candidates = [
            config('app.front_url_psicologo'),
            config('app.front_url_user'),
            config('app.front_url'),
            config('app.frontend_url'),
            'https://minder.mindmeet.com.mx',
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeAbsoluteUrl($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return 'https://minder.mindmeet.com.mx';
    }

    protected function normalizeAbsoluteUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (!Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://' . ltrim($url, '/');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return rtrim($url, '/');
    }

}
