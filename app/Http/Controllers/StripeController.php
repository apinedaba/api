<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use App\Models\AppointmentCart;
use Illuminate\Support\Facades\Auth;


class StripeController extends Controller
{
    public function createPaymentIntent()
    {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

        $patient = Auth::user();

        $cart = AppointmentCart::where('patient_id', $patient->id)
            ->where('estado', 'pendiente')
            ->first();

        if (!$cart) {
            return response()->json(['message' => 'No hay una cita pendiente para pagar.'], 404);
        }

        $intent = PaymentIntent::create([
            'amount' => $cart->precio * 100, // MXN
            'currency' => 'mxn',
            'metadata' => [
                'appointment_cart_id' => $cart->id,
                'patient_id' => $patient->id,
            ],
        ]);
        $cart->update([
            'payment_intent_id' => $intent->id // GUÁRDALO AQUÍ
        ]);

        return response()->json([
            'clientSecret' => $intent->client_secret
        ]);
    }

    public function confirmarPago(Request $request)
    {
        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

        $intentId = $request->query('intent');
        if (!$intentId) {
            return response()->json(['message' => 'Falta el payment_intent_id'], 400);
        }

        $cart = \App\Models\AppointmentCart::with('user')
            ->where('payment_intent_id', $intentId)
            ->first();

        if (!$cart) {
            $existing = \App\Models\Appointment::where('cart_id', function ($q) use ($intentId) {
                $q->select('id')->from('appointment_carts')->where('payment_intent_id', $intentId)->limit(1);
            })->first();

            return $existing
                ? response()->json($existing)
                : response()->json(['message' => 'No se encontró carrito o cita'], 404);
        }

        $existing = \App\Models\Appointment::where('cart_id', $cart->id)->first();
        if ($existing) {
            return response()->json($existing);
        }

        $intent = \Stripe\PaymentIntent::retrieve($intentId);
        if ($intent->status !== 'succeeded') {
            return response()->json(['message' => 'El pago no fue exitoso'], 402);
        }

        $inicio = "{$cart->fecha} {$cart->hora}";
        $fin = \Carbon\Carbon::parse($inicio)->addHours($cart->duracion);

        $appointment = \App\Models\Appointment::create([
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
        ]);

        $cart->update([
            'estado' => 'pagado',
            'payment_intent_id' => null,
            'stripe_session_id' => null,
        ]);

        return response()->json($appointment);
    }


}
