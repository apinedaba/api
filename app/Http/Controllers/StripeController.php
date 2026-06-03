<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\HandleStripeEventJob;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Checkout\Session;
use Stripe\Webhook;
use Stripe\Subscription as StripeSubscription;

use App\Models\AppointmentCart;
use App\Models\Appointment;
use App\Models\PatientUser;
use App\Services\AppointmentService;
use App\Services\CheckoutPricingService;
use App\Services\SubscriptionStatusService;
use App\Models\User;
use App\Models\Subscription;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\BillingPortal\Session as BillingPortalSession;
use App\Models\Payment;
use App\Notifications\SessionPaymentRegisteredNotification;
use App\Services\TwilioWhatsAppService;
use Carbon\Carbon;

class StripeController extends Controller
{
    protected $stripe_secretkey;
    protected $service;
    protected $subscriptionStatusService;
    protected $pricingService;

    public function __construct(
        AppointmentService $service,
        SubscriptionStatusService $subscriptionStatusService,
        CheckoutPricingService $pricingService
    )
    {
        // Usa tu secret actual (puede ser el mismo en local y prod, tú ya lo tenías así)
        $this->stripe_secretkey = config('services.stripe.secret_key') ?? env('STRIPE_SECRET_KEY');
        $this->service = $service;
        $this->subscriptionStatusService = $subscriptionStatusService;
        $this->pricingService = $pricingService;
    }

    public function createPaymentIntent(Request $request)
    {
        Stripe::setApiKey($this->stripe_secretkey);

        $patient = $request->user();

        $cart = AppointmentCart::where('patient_id', $patient->id)
            ->where('estado', 'pendiente')
            ->first();

        if (!$cart) {
            return response()->json(['message' => 'No hay una cita pendiente para pagar.'], 404);
        }

        $pricing = $this->pricingService->buildFromCart($cart, $request->input('cuando'));
        $cart->forceFill($pricing)->save();
        $amount = (int) round($pricing['total_charge_amount'] * 100);

        $intent = PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'mxn',
            'metadata' => [
                'appointment_cart_id' => $cart->id,
                'patient_id' => $patient->id,
                'user_id' => $cart->user_id,
                'charge_mode' => $pricing['charge_mode'],
                'session_base_amount' => $pricing['session_base_amount'],
                'charge_subtotal_amount' => $pricing['charge_subtotal_amount'],
                'platform_fee_amount' => $pricing['platform_fee_amount'],
                'total_charge_amount' => $pricing['total_charge_amount'],
                'remaining_balance_amount' => $pricing['remaining_balance_amount'],
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
        $method = $request->input('paymentMethod') ?? $request->input('cuando');
        if (!$intentId) {
            return response()->json(['message' => 'Falta el payment_intent_id'], 400);
        }
        $cart = AppointmentCart::with('user')
            ->where('payment_intent_id', $intentId)
            ->first();

        if (!$cart && $request->filled('cartId')) {
            $cart = AppointmentCart::with('user')->find($request->input('cartId'));
        }

        if (!$cart) {
            $existing = Appointment::where('cart_id', function ($q) use ($intentId) {
                $q->select('id')->from('appointment_carts')->where('payment_intent_id', $intentId)->limit(1);
            })->first();

            return $existing
                ? response()->json($existing)
                : response()->json(['message' => 'No se encontró carrito o cita'], 404);
        }

        $intent = PaymentIntent::retrieve($intentId);
        if ($intent->status !== 'succeeded') {
            return response()->json(['message' => 'El pago no fue exitoso'], 402);
        }

        // Relación + sala
        $chargeMode = $this->pricingService->normalizeChargeMode(
            $method ?: data_get($intent, 'metadata.charge_mode')
        );
        $pricing = $this->pricingService->buildFromCart($cart, $chargeMode);
        $cart->forceFill($pricing)->save();

        $existing = Appointment::where('cart_id', $cart->id)->first();
        if ($existing) {
            $this->ensureCompletedPayment($cart, $existing, $intent, $pricing, 'card');
            $this->generarEnlace($cart->user_id, $cart->patient_id);
            $cart->update([
                'estado' => 'pagado',
                'payment_intent_id' => null,
                'stripe_session_id' => null,
                'appointment_id' => $existing->id
            ]);

            return response()->json($existing);
        }

        $relation = $this->service->ensureRelationshipAndRoom($cart->user_id, $cart->patient_id);

        $appointment = $this->createPaidAppointment($cart, $relation->video_call_room, $chargeMode);

        $this->ensureCompletedPayment($cart, $appointment, $intent, $pricing, 'card');
        $this->generarEnlace($cart->user_id, $cart->patient_id);
        $cart->update([
            'estado' => 'pagado',
            'payment_intent_id' => null,
            'stripe_session_id' => null,
            'appointment_id' => $appointment->id
        ]);


        return response()->json($appointment);
    }

    private function createPaidAppointment(AppointmentCart $cart, ?string $videoCallRoom, string $chargeMode): Appointment
    {
        $cart->loadMissing(['user', 'patient']);

        $start = Carbon::parse("{$cart->fecha} {$cart->hora}");
        $duration = is_numeric($cart->duracion) ? (float) $cart->duracion : 1.0;
        $minutes = $duration <= 8 ? (int) round($duration * 60) : (int) round($duration);
        $end = $start->copy()->addMinutes(max($minutes, 1));
        $patientName = $cart->patient?->name ?: 'Paciente MindMeet';

        return Appointment::create([
            'user' => $cart->user_id,
            'patient' => $cart->patient_id,
            'start' => $start,
            'end' => $end,
            'title' => 'Sesión con ' . $patientName,
            'statusUser' => 'Pending Approve',
            'statusPatient' => 'Pending Approve',
            'state' => $chargeMode === 'avg' ? 'Pendiente de liquidar' : 'Creado',
            'cart_id' => $cart->id,
            'video_call_room' => $videoCallRoom,
            'extendedProps' => [
                'tipoSesion' => $cart->tipoSesion,
                'formato' => $cart->formato,
                'payment_status' => 'paid',
                'charge_mode' => $chargeMode,
            ],
            'notification_meta' => [],
        ]);
    }

    private function ensureCompletedPayment(
        AppointmentCart $cart,
        Appointment $appointment,
        PaymentIntent $intent,
        array $pricing,
        string $paymentMethod
    ): Payment {
        $paymentPayload = [
            'user_id' => $cart->user_id,
            'payer_type' => 'patient',
            'appointment_id' => $appointment->id,
            'patient_id' => $cart->patient_id,
            'amount' => $pricing['total_charge_amount'],
            'currency' => 'MXN',
            'payment_method' => $paymentMethod,
            'status' => 'completed',
            'receipt_url' => data_get($intent, 'charges.data.0.receipt_url'),
            'session_base_amount' => $pricing['session_base_amount'],
            'charge_subtotal_amount' => $pricing['charge_subtotal_amount'],
            'platform_fee_rate' => $pricing['platform_fee_rate'],
            'platform_fee_amount' => $pricing['platform_fee_amount'],
            'total_charge_amount' => $pricing['total_charge_amount'],
            'psychologist_amount' => $pricing['psychologist_amount'],
            'remaining_balance_amount' => $pricing['remaining_balance_amount'],
            'charge_mode' => $pricing['charge_mode'],
            'payout_status' => $pricing['payout_status'],
        ];

        if (Schema::hasColumn('payments', 'concepto')) {
            $paymentPayload['concepto'] = $pricing['charge_mode'] === 'avg'
                ? 'session_deposit'
                : 'session_payment';
        }

        $payment = Payment::updateOrCreate(
            ['stripe_payment_id' => $intent->id],
            $paymentPayload
        );

        if ($payment->wasRecentlyCreated) {
            try {
                $cart->user?->notify(new SessionPaymentRegisteredNotification($appointment, $payment));
            } catch (\Throwable $th) {
                Log::warning('Session payment notification failed', [
                    'payment_id' => $payment->id,
                    'appointment_id' => $appointment->id,
                    'message' => $th->getMessage(),
                ]);
            }

            try {
                if ($cart->user) {
                    app(TwilioWhatsAppService::class)->sendToUser(
                        $cart->user,
                        $this->paymentRegisteredWhatsAppMessage($appointment, $payment)
                    );
                }
            } catch (\Throwable $th) {
                Log::warning('Session payment WhatsApp notification failed', [
                    'payment_id' => $payment->id,
                    'appointment_id' => $appointment->id,
                    'message' => $th->getMessage(),
                ]);
            }
        }

        return $payment;
    }

    private function paymentRegisteredWhatsAppMessage(Appointment $appointment, Payment $payment): string
    {
        $patient = $appointment->patient()->first();
        $start = \Carbon\Carbon::parse($appointment->start)->timezone(config('app.timezone'));
        $agendaUrl = $this->resolvePsychologistFrontendUrl() . '/agenda';
        $concept = $payment->concepto === 'session_deposit' ? 'anticipo' : 'pago';
        $amount = '$' . number_format((float) $payment->amount, 2) . ' ' . ($payment->currency ?: 'MXN');

        return "MindMeet: se registro un {$concept} de {$amount} para una sesion.\n"
            . "Paciente: " . ($patient?->name ?: 'Paciente MindMeet') . "\n"
            . "Fecha: " . $start->format('d/m/Y H:i') . "\n"
            . "La cita ya esta en tu agenda: {$agendaUrl}";
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

        $pricing = $this->pricingService->buildFromCart($cart, $request->input('cuando'));
        $cart->forceFill($pricing)->save();
        $amount = (int) round($pricing['total_charge_amount'] * 100);

        // 🔒 Normaliza FRONTEND_URL con fallback
        $frontend = $this->resolvePatientFrontendUrl();

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
                    'charge_mode' => $pricing['charge_mode'],
                    'session_base_amount' => $pricing['session_base_amount'],
                    'charge_subtotal_amount' => $pricing['charge_subtotal_amount'],
                    'platform_fee_amount' => $pricing['platform_fee_amount'],
                    'total_charge_amount' => $pricing['total_charge_amount'],
                    'remaining_balance_amount' => $pricing['remaining_balance_amount'],
                ],
            ],
            'success_url' => $frontend . '/pago/oxxo/exito?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontend . '/pago/oxxo/cancelado',
            'metadata' => [
                'type' => 'session_pago_oxxo',
                'appointment_cart_id' => $cart->id,
                'patient_id' => $patient->id,
                'user_id' => $cart->user_id,
                'charge_mode' => $pricing['charge_mode'],
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

        $pricing = $this->pricingService->buildFromCart($cart, $request->input('cuando'));
        $cart->forceFill($pricing)->save();
        $amount = (int) round($pricing['total_charge_amount'] * 100);

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
                'charge_mode' => $pricing['charge_mode'],
                'session_base_amount' => $pricing['session_base_amount'],
                'charge_subtotal_amount' => $pricing['charge_subtotal_amount'],
                'platform_fee_amount' => $pricing['platform_fee_amount'],
                'total_charge_amount' => $pricing['total_charge_amount'],
                'remaining_balance_amount' => $pricing['remaining_balance_amount'],
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
                                $chargeMode = $this->pricingService->normalizeChargeMode($meta['charge_mode'] ?? null);
                                $pricing = $this->pricingService->buildFromCart($cart, $chargeMode);
                                $cart->forceFill($pricing)->save();
                                $relation = $this->service->ensureRelationshipAndRoom($userId, $patientId);

                                $appointment = Appointment::where('cart_id', $cart->id)->first()
                                    ?: $this->createPaidAppointment($cart, $relation->video_call_room, $chargeMode);

                                $this->ensureCompletedPayment($cart, $appointment, $pi, $pricing, 'oxxo');

                                $this->generarEnlace($userId, $patientId);

                                $cart->update([
                                    'estado' => 'pagado',
                                    'payment_intent_id' => $pi->id,
                                    'stripe_session_id' => $cart->stripe_session_id, // lo conservas si quieres
                                    'appointment_id' => $appointment->id,
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
        return response()->json(
            $this->subscriptionStatusService->summarize($request->user())
        );
    }

    public function createSubscriptionCheckoutSession(Request $request)
    {
        Stripe::setApiKey($this->stripe_secretkey);
        $request->validate(['plan_id' => 'required|string']);
        $user = $request->user();
        $frontendUrl = $this->resolvePsychologistFrontendUrl();
        $subscription = $user->subscription()->first();
        if (!$user->stripe_id) {
            $customer = \Stripe\Customer::create(['email' => $user->email, 'name' => $user->name]);
            $user->stripe_id = $customer->id;
            $user->save();
        }

        $existingSubscription = $this->findReusableStripeSubscription($user->stripe_id);

        if ($existingSubscription) {
            $subscription = $this->syncUserSubscriptionFromStripe($user, $existingSubscription);

            if ($this->canReactivateStripeSubscription($existingSubscription)) {
                $reactivatedSubscription = StripeSubscription::update($existingSubscription->id, [
                    'cancel_at_period_end' => false,
                ]);

                $this->syncUserSubscriptionFromStripe($user, $reactivatedSubscription);

                return response()->json([
                    'url' => $frontendUrl . '/perfil/suscripcion?status=reactivated',
                ]);
            }

            if ($this->shouldRedirectToPortal($existingSubscription)) {
                $portalSession = BillingPortalSession::create([
                    'customer' => $user->stripe_id,
                    'return_url' => $frontendUrl . '/perfil/suscripcion',
                ]);

                return response()->json([
                    'url' => $portalSession->url,
                ]);
            }
        }


        $sessionData = [
            'mode' => 'subscription',
            'customer' => $user->stripe_id,
            'line_items' => [['price' => $request->plan_id, 'quantity' => 1]],
            'success_url' => $frontendUrl . '/perfil/suscripcion?status=success',
            'cancel_url' => $frontendUrl . '/perfil/suscripcion?status=canceled',
            'metadata' => ['user_id' => $user->id],
            'locale' => 'es-419',
        ];
        $hasHadAnySubscription = isset($subscription->id)
            && filled($subscription->stripe_status)
            && $subscription->stripe_status !== 'pending';

        Log::info('Has had any subscription before: ' . ($hasHadAnySubscription ? 'true' : 'false'));
        if (!$hasHadAnySubscription) {
            $sessionData['subscription_data'] = [
                'trial_period_days' => 15, // ¡Aquí defines la duración de la prueba!
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
        $frontendUrl = $this->resolvePsychologistFrontendUrl();

        $portalSession = BillingPortalSession::create([
            'customer' => $user->stripe_id,
            'return_url' => $frontendUrl . '/perfil/suscripcion',
        ]);

        return response()->json(['url' => $portalSession->url]);
    }
    public function changeSubscriptionPlan(Request $request)
    {
        Stripe::setApiKey($this->stripe_secretkey);
        $request->validate(['plan_id' => 'required|string']);

        $user = $request->user();
        if (!$user->stripe_id) {
            return response()->json(['message' => 'El usuario no tiene cliente de Stripe.'], 422);
        }

        $existingSubscription = $this->findReusableStripeSubscription($user->stripe_id);
        if (!$existingSubscription) {
            return response()->json(['message' => 'No encontramos una suscripcion activa para actualizar.'], 404);
        }

        $currentItem = data_get($existingSubscription, 'items.data.0');
        $currentPriceId = data_get($currentItem, 'price.id') ?: data_get($currentItem, 'plan.id');
        if ($currentPriceId === $request->plan_id) {
            return response()->json(['message' => 'Tu suscripcion ya usa este plan.'], 200);
        }

        $updatedSubscription = StripeSubscription::update($existingSubscription->id, [
            'cancel_at_period_end' => false,
            'proration_behavior' => 'create_prorations',
            'items' => [
                [
                    'id' => data_get($currentItem, 'id'),
                    'price' => $request->plan_id,
                ],
            ],
        ]);

        Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'stripe_id' => $updatedSubscription->id,
                'stripe_plan' => data_get($updatedSubscription, 'items.data.0.price.id'),
                'stripe_status' => $updatedSubscription->status,
                'trial_ends_at' => $updatedSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($updatedSubscription->trial_end) : null,
                'ends_at' => null,
            ]
        );

        return response()->json([
            'message' => 'Tu plan se actualizo correctamente.',
            'subscription_id' => $updatedSubscription->id,
        ]);
    }
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');
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
        try {
            $payload = $request->getContent();
            $sigHeader = $request->header('Stripe-Signature');
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook_secret')
            );
            if ($event->type == 'checkout.session.completed') {
                $session = $event->data->object;
                if ($session->mode == 'subscription') {
                    $user = User::find($session->metadata->user_id);
                    Subscription::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'stripe_id' => null,
                            'stripe_plan' => null,
                            'stripe_status' => 'pending',
                            'trial_ends_at' => null,
                            'ends_at' => null,
                        ]
                    );
                }
            }

            HandleStripeEventJob::dispatch($event);
            return response()->json(['received' => true], 200);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook error', [
                'message' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Invalid webhook'], 400);
        }
    }

    protected function findReusableStripeSubscription(string $customerId): mixed
    {
        $subscriptions = StripeSubscription::all([
            'customer' => $customerId,
            'status' => 'all',
            'limit' => 20,
        ])->data;

        foreach ([['active', 'trialing'], ['past_due', 'unpaid', 'paused'], ['incomplete']] as $statusGroup) {
            foreach ($subscriptions as $subscription) {
                if (in_array($subscription->status, $statusGroup, true)) {
                    return $subscription;
                }
            }
        }

        return null;
    }

    protected function syncUserSubscriptionFromStripe(User $user, mixed $stripeSubscription): Subscription
    {
        $subscription = Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'stripe_id' => $stripeSubscription->id,
                'stripe_plan' => data_get($stripeSubscription, 'items.data.0.price.id')
                    ?: data_get($stripeSubscription, 'items.data.0.plan.id'),
                'stripe_status' => $stripeSubscription->status,
                'trial_ends_at' => $stripeSubscription->trial_end
                    ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
                    : null,
                'ends_at' => $this->resolveStripeSubscriptionEndsAt($stripeSubscription),
            ]
        );

        Log::info('Stripe subscription reconciled before checkout', [
            'user_id' => $user->id,
            'stripe_customer_id' => $user->stripe_id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'stripe_status' => $stripeSubscription->status,
        ]);

        return $subscription;
    }

    protected function resolveStripeSubscriptionEndsAt(mixed $stripeSubscription): ?Carbon
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

    protected function canReactivateStripeSubscription($subscription): bool
    {
        return (bool) ($subscription->cancel_at_period_end ?? false)
            && in_array($subscription->status, ['active', 'trialing', 'past_due', 'unpaid'], true);
    }

    protected function shouldRedirectToPortal($subscription): bool
    {
        return in_array($subscription->status, ['active', 'trialing', 'past_due', 'unpaid', 'incomplete', 'paused'], true);
    }

    protected function resolvePsychologistFrontendUrl(): string
    {
        $candidates = [
            config('app.front_url_psicologo'),
            app()->environment('local') ? 'http://localhost:5173' : null,
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

        return app()->environment('local') ? 'http://localhost:5173' : 'https://minder.mindmeet.com.mx';
    }

    protected function resolvePatientFrontendUrl(): string
    {
        $candidates = [
            config('app.front_url'),
            config('app.front_url_user'),
            config('app.frontend_url'),
            config('app.url'),
            app()->environment('local') ? 'http://localhost:5173' : null,
            'https://mindmeet.com.mx',
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeAbsoluteUrl($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return app()->environment('local') ? 'http://localhost:5173' : 'https://mindmeet.com.mx';
    }

    protected function normalizeAbsoluteUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            if (str_starts_with($url, 'localhost') || str_starts_with($url, '127.0.0.1')) {
                $url = 'http://' . ltrim($url, '/');
            } else {
                $url = 'https://' . ltrim($url, '/');
            }
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return rtrim($url, '/');
    }
}
