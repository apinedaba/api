<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\HandleStripeEventJob;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Checkout\Session;
use Stripe\Webhook;

use App\Models\AppointmentCart;
use App\Models\Appointment;
use App\Models\PatientUser;
use App\Services\AppointmentService;
use App\Models\User;
use App\Models\Subscription;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\BillingPortal\Session as BillingPortalSession;
use App\Models\Payment;

class StripeController extends Controller
{
    protected $stripe_secretkey;
    protected $service;

    public function __construct(AppointmentService $service)
    {
        // Usa tu secret actual (puede ser el mismo en local y prod, tú ya lo tenías así)
        $this->stripe_secretkey = env('STRIPE_SECRET_KEY');
        $this->service = $service;
    }

    public function createPaymentIntent()
    {
        Stripe::setApiKey($this->stripe_secretkey);

        $patient = Auth::user();

        $cart = AppointmentCart::where('patient_id', $patient->id)
            ->where('estado', 'pendiente')
            ->first();

        if (!$cart) {
            return response()->json(['message' => 'No hay una cita pendiente para pagar.'], 404);
        }

        $amount = (int) round($cart->precio * 100); // MXN -> centavos

        $intent = PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'mxn',
            'metadata' => [
                'appointment_cart_id' => $cart->id,
                'patient_id' => $patient->id,
                'user_id' => $cart->user_id,
                'type' => 'session_pago_card',
            ],
        ]);

        $cart->update([
            'payment_intent_id' => $intent->id
        ]);

        return response()->json([
            'clientSecret' => $intent->client_secret
        ]);
    }

    public function confirmarPago(Request $request)
    {

        Stripe::setApiKey($this->stripe_secretkey);

        $intentId = $request->intentId;
        $method = $request->paymentMethod;
        if (!$intentId) {
            return response()->json(['message' => 'Falta el payment_intent_id'], 400);
        }

        if ($method == "avg") {
            return response()->json("Pagaras solo el anticipo");
        }
        $cart = AppointmentCart::with('user')
            ->where('payment_intent_id', $intentId)
            ->first();

        if (!$cart) {
            $existing = Appointment::where('cart_id', function ($q) use ($intentId) {
                $q->select('id')->from('appointment_carts')->where('payment_intent_id', $intentId)->limit(1);
            })->first();

            return $existing
                ? response()->json($existing)
                : response()->json(['message' => 'No se encontró carrito o cita'], 404);
        }

        $existing = Appointment::where('cart_id', $cart->id)->first();
        if ($existing) {
            return response()->json($existing);
        }

        $intent = PaymentIntent::retrieve($intentId);
        if ($intent->status !== 'succeeded') {
            return response()->json(['message' => 'El pago no fue exitoso'], 402);
        }

        // Relación + sala
        $relation = $this->service->ensureRelationshipAndRoom($cart->user_id, $cart->patient_id);

        $inicio = "{$cart->fecha} {$cart->hora}";
        $fin = \Carbon\Carbon::parse($inicio)->addHours($cart->duracion);

        $appointment = Appointment::create([
            'user' => $cart->user_id,
            'patient' => $cart->patient_id,
            'start' => $inicio,
            'end' => $fin,
            'title' => 'Sesión con ' . ($cart->user->contacto['publicName'] ?? $cart->user->name),
            'statusUser' => 'Pending Approve',
            'statusPatient' => 'Pending Approve',
            'state' => 'Creado',
            'precio' => $cart->precio,
            'tipoSesion' => $cart->tipoSesion,
            'cart_id' => $cart->id,
            'video_call_room' => $relation->video_call_room,
        ]);

        $payment = Payment::create([
            'user_id' => $cart->user_id,
            'payer_type' => 'patient',
            'appointment_id' => $appointment->id,
            'patient_id' => $cart->patient_id,
            'amount' => $cart->precio,
            'currency' => 'MXN',
            'payment_method' => 'card',
            'status' => 'completed',
            'stripe_payment_id' => $intent->id,
            'receipt_url' => $intent->charges->data[0]->receipt_url ?? null,
        ]);
        $this->generarEnlace($cart->user_id, $cart->patient_id);
        $cart->update([
            'estado' => 'pagado',
            'payment_intent_id' => null,
            'stripe_session_id' => null,
            'appointment_id' => $appointment->id
        ]);


        return response()->json($appointment);
    }

    public function generarEnlace($user, $patient)
    {
        $checkExist = PatientUser::where('user', $user)->where('patient', $patient)->first();
        if (isset($checkExist->id)) {
            return true;
        }

        $enlace = PatientUser::create([
            'user' => $user,
            'patient' => $patient,
            'status' => 'Vinculado desde portal'
        ]);

        return (bool) $enlace;
    }

    /**
     * ✅ OXXO Checkout basado en carrito pendiente
     * - Crea la sesión de Checkout con payment_method_types ['oxxo']
     * - Propaga metadata al PaymentIntent (clave para el webhook)
     * - Guarda stripe_session_id en el carrito
     */
    public function oxxoCheckout(Request $request)
    {
        Stripe::setApiKey($this->stripe_secretkey);

        $patient = $request->user();

        $cart = AppointmentCart::where('patient_id', $patient->id)
            ->where('estado', 'pendiente')
            ->first();

        if (!$cart) {
            return response()->json(['message' => 'No hay una cita pendiente para pagar.'], 404);
        }

        $amount = (int) round($cart->precio * 100);

        // 🔒 Normaliza FRONTEND_URL con fallback
        $frontend = trim(env('FRONTEND_URL') ?: config('app.url') ?: '', " \t\n\r\0\x0B/");
        if (!preg_match('#^https?://#i', $frontend)) {
            // Si no trae http/https, agrega http:// como fallback en local
            $frontend = 'http://' . $frontend;
        }

        $session = \Stripe\Checkout\Session::create([
            'mode' => 'payment',
            'payment_method_types' => ['oxxo'],
            'customer_email' => $patient->email,
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'mxn',
                        'product_data' => ['name' => 'Sesión MindMeet'],
                        'unit_amount' => $amount,
                    ],
                    'quantity' => 1,
                ]
            ],
            'payment_method_options' => [
                'oxxo' => ['expires_after_days' => 3],
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'type' => 'session_pago_oxxo',
                    'appointment_cart_id' => $cart->id,
                    'patient_id' => $patient->id,
                    'user_id' => $cart->user_id,
                ],
            ],
            'success_url' => $frontend . '/pago/oxxo/exito?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontend . '/pago/oxxo/cancelado',
            'metadata' => [
                'type' => 'session_pago_oxxo',
                'appointment_cart_id' => $cart->id,
                'patient_id' => $patient->id,
                'user_id' => $cart->user_id,
            ],
            'locale' => 'es-419',
        ]);

        $cart->update(['stripe_session_id' => $session->id]);

        return response()->json(['url' => $session->url, 'clientSecret ' => $session->id]);
    }
    public function createOxxoIntent(Request $request)
    {
        Stripe::setApiKey($this->stripe_secretkey);

        $patient = $request->user();

        $cart = AppointmentCart::where('patient_id', $patient->id)
            ->where('estado', 'pendiente')
            ->first();

        if (!$cart) {
            return response()->json(['message' => 'No hay una cita pendiente para pagar.'], 404);
        }

        $amount = (int) round($cart->precio * 100); // MXN -> centavos

        $pi = PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'mxn',
            'payment_method_types' => ['oxxo'],
            'receipt_email' => $patient->email, // manda recibo
            'payment_method_options' => [
                'oxxo' => ['expires_after_days' => 2], // ajusta 1–30
            ],
            'metadata' => [
                'type' => 'session_pago_oxxo',
                'appointment_cart_id' => $cart->id,
                'patient_id' => $patient->id,
                'user_id' => $cart->user_id,
            ],
        ]);

        // Guarda referencia para trazabilidad (útil si haces polling desde front)
        $cart->update([
            'payment_intent_id' => $pi->id,
            // no cambies estado a pagado; aún no está pagado, solo se generará el voucher
            // si quieres, puedes marcar 'voucher_generado' después del confirm en front
        ]);

        return response()->json([
            'clientSecret' => $pi->client_secret,
            'id' => $pi->id,
        ]);
    }
    /**
     * 🔔 Webhook de Stripe para OXXO (y otros pagos)
     * - payment_intent.succeeded => pago acreditado (OXXO)
     * - payment_intent.payment_failed => expiró/no se pagó
     */
    public function webhook(Request $request)
    {
        $endpointSecret = config('services.stripe.webhook'); // STRIPE_WEBHOOK_SECRET en .env
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\Exception $e) {
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        switch ($event->type) {
            // Checkout completado (solo significa voucher generado en OXXO)
            case 'checkout.session.completed': {
                $session = $event->data->object;
                Log::info('Checkout session completed (voucher generado): ' . $session->id);
                // Puedes actualizar estado del cart a "voucher_generado" si gustas:
                if (!empty($session->metadata->appointment_cart_id)) {
                    AppointmentCart::where('id', $session->metadata->appointment_cart_id)
                        ->update(['estado' => 'voucher_generado']);
                }
                break;
            }

            // 💰 Para OXXO, el pago real llega aquí (acreditado)
            case 'payment_intent.succeeded': {
                $pi = $event->data->object; // \Stripe\PaymentIntent
                $meta = (array) ($pi->metadata ?? []);

                if (($meta['type'] ?? null) === 'session_pago_oxxo') {
                    $cartId = $meta['appointment_cart_id'] ?? null;
                    $patientId = $meta['patient_id'] ?? null;
                    $userId = $meta['user_id'] ?? null;

                    if ($cartId && $patientId && $userId) {
                        $cart = AppointmentCart::with('user')->find($cartId);
                        if ($cart && $cart->estado !== 'pagado') {
                            // Relación + sala
                            $relation = $this->service->ensureRelationshipAndRoom($userId, $patientId);

                            $inicio = "{$cart->fecha} {$cart->hora}";
                            $fin = \Carbon\Carbon::parse($inicio)->addHours($cart->duracion);

                            $appointment = Appointment::firstOrCreate(
                                ['cart_id' => $cart->id],
                                [
                                    'user' => $userId,
                                    'patient' => $patientId,
                                    'start' => $inicio,
                                    'end' => $fin,
                                    'title' => 'Sesión con ' . ($cart->user->contacto['publicName'] ?? $cart->user->name),
                                    'statusUser' => 'Pending Approve',
                                    'statusPatient' => 'Pending Approve',
                                    'state' => 'Creado',
                                    'precio' => $cart->precio,
                                    'tipoSesion' => $cart->tipoSesion,
                                    'video_call_room' => $relation->video_call_room,
                                ]
                            );

                            $payment = Payment::create([
                                'user_id' => $cart->user_id,
                                'payer_type' => 'patient',
                                'appointment_id' => $appointment->id,
                                'patient_id' => $cart->patient_id,
                                'amount' => $cart->precio,
                                'currency' => 'MXN',
                                'payment_method' => 'oxxo',
                                'status' => 'completed',
                                'stripe_payment_id' => $pi->id,
                                'receipt_url' => $cart->stripe_session_id ?? null,
                            ]);

                            $this->generarEnlace($userId, $patientId);

                            $cart->update([
                                'estado' => 'pagado',
                                'payment_intent_id' => $pi->id,
                                'stripe_session_id' => $cart->stripe_session_id, // lo conservas si quieres
                            ]);

                            Log::info("OXXO pago acreditado. Cart {$cart->id} -> Appointment {$appointment->id}");
                        }
                    }
                }

                break;
            }

            // ❌ Voucher expiró / pago falló
            case 'payment_intent.payment_failed': {
                $pi = $event->data->object;
                $meta = (array) ($pi->metadata ?? []);
                if (($meta['type'] ?? null) === 'session_pago_oxxo' && !empty($meta['appointment_cart_id'])) {
                    AppointmentCart::where('id', $meta['appointment_cart_id'])
                        ->update(['estado' => 'cancelado']);
                    Log::warning("OXXO voucher expiró / fallo. Cart {$meta['appointment_cart_id']} cancelado.");
                }
                break;
            }
        }

        return response()->json(['received' => true]);
    }

    public function getSubscriptionStatus(Request $request)
    {
        return response()->json($request->user()->subscription);
    }

    public function createSubscriptionCheckoutSession(Request $request)
    {
        Stripe::setApiKey($this->stripe_secretkey);
        $request->validate(['plan_id' => 'required|string']);
        $user = $request->user();
        $subscription = $user->subscription()->first();
        if (!$user->stripe_id) {
            $customer = \Stripe\Customer::create(['email' => $user->email, 'name' => $user->name]);
            $user->stripe_id = $customer->id;
            $user->save();
        }


        $sessionData = [
            'mode' => 'subscription',
            'customer' => $user->stripe_id,
            'line_items' => [['price' => $request->plan_id, 'quantity' => 1]],
            'success_url' => env('FRONTEND_URL_USER') . '/perfil/suscripcion?status=success',
            'cancel_url' => env('FRONTEND_URL_USER') . '/perfil/suscripcion?status=canceled',
            'metadata' => ['user_id' => $user->id],
            'locale' => 'es-419',
        ];
        $hasHadRealStripeSubscription = false;
        if (isset($subscription->stripe_status)) {
            $hasHadRealStripeSubscription = $subscription->stripe_status === 'trialing' || $subscription->stripe_status === 'trial_expired' || $subscription->stripe_status === 'trial';
        }

        Log::info('Has had real stripe subscription: ' . $hasHadRealStripeSubscription . ' - ' . $subscription->stripe_status);
        if (!$hasHadRealStripeSubscription) {
            $sessionData['subscription_data'] = [
                'trial_period_days' => 10, // ¡Aquí defines la duración de la prueba!
            ];
        }
        $session = CheckoutSession::create($sessionData);

        return response()->json(['url' => $session->url]);
    }

    public function createCustomerPortalSession(Request $request)
    {
        Stripe::setApiKey($this->stripe_secretkey);
        $user = $request->user();
        if (!$user->stripe_id) {
            return response()->json(['error' => 'Usuario no tiene cliente de Stripe.'], 400);
        }

        $portalSession = BillingPortalSession::create([
            'customer' => $user->stripe_id,
            'return_url' => env('FRONTEND_URL_USER') . '/perfil/suscripcion',
        ]);

        return response()->json(['url' => $portalSession->url]);
    }
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = env('STRIPE_WEBHOOK_SECRET');
        $event = null;

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                if ($session->mode == 'subscription') {
                    $this->handleNewSubscription($session);
                }
                break;
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $this->handleSubscriptionChange($event->data->object);
                break;
            case 'invoice.payment_failed':
                $this->handleFailedPayment($event->data->object);
                break;
        }

        return response()->json(['status' => 'success']);
    }
    public function handle(Request $request)
    {
        Log::info('Stripe webhook received');
        try {
            $payload = $request->getContent();
            $sigHeader = $request->header('Stripe-Signature');
            Log::info('Stripe webhook processed 1');
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook_secret')
            );
            Log::info('Stripe webhook processed 2');
            // 🚀 SOLO despachar el job
            HandleStripeEventJob::dispatch($event);
            Log::info('Stripe webhook dispatched');
            // ⚡ RESPUESTA INMEDIATA A STRIPE
            return response()->json(['received' => true], 200);

        } catch (\Throwable $e) {
            Log::error('Stripe webhook error', [
                'message' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Invalid webhook'], 400);
        }
    }
}
