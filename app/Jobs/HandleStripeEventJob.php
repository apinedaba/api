<?php

namespace App\Jobs;

use App\Models\AppointmentCart;
use App\Models\PatientUser;
use App\Models\Payment;
use App\Models\User;
use App\Models\Subscription;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\AppointmentService;

class HandleStripeEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $service;
    public $event;

    public function __construct($event, AppointmentService $service)
    {
        $this->event = $event;
        $this->service = $service;
    }

    public function handle()
    {
        $event = $this->event;
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

                            $appointment = AppointmentCart::firstOrCreate(
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
