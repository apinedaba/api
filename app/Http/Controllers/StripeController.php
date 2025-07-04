<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use App\Models\AppointmentCart;
use App\Models\Appointment;
use Illuminate\Support\Facades\Auth;
use App\Services\AppointmentService; // 👈 Agregado

class StripeController extends Controller
{
    protected $stripe_secretkey;
    public function __construct()
    {
        $this->stripe_secretkey = env('APP_ENV') === 'local' ? env('STRIPE_SECRET_KEY'): env('STRIPE_SECRET_KEY_LIVE');
        // Puedes agregar middleware aquí si es necesario
        // $this->middleware('auth:patient');
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

        $intent = PaymentIntent::create([
            'amount' => $cart->precio * 100, // MXN
            'currency' => 'mxn',
            'metadata' => [
                'appointment_cart_id' => $cart->id,
                'patient_id' => $patient->id,
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
        \Stripe\Stripe::setApiKey($this->stripe_secretkey);

        $intentId = $request->query('intent');
        if (!$intentId) {
            return response()->json(['message' => 'Falta el payment_intent_id'], 400);
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

        // 🟢 🔑 Aquí usamos el Service:
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
            'video_call_room' => $relation->video_call_room, // 👈 NUEVO
        ]);

        $cart->update([
            'estado' => 'pagado',
            'payment_intent_id' => null,
            'stripe_session_id' => null,
        ]);

        return response()->json($appointment);
    }
}
