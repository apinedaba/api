<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminSessionOperationsController extends Controller
{
    public function index()
    {
        return Inertia::render('SessionOperations', [
            'patients' => Patient::query()
                ->select('id', 'name', 'email', 'phone', 'contacto', 'activo')
                ->with(['connections.user:id,name,email'])
                ->orderBy('name')
                ->get(),
            'psychologists' => User::query()
                ->select('id', 'name', 'email', 'activo', 'identity_verification_status')
                ->orderBy('name')
                ->get(),
            'recentAppointments' => Appointment::query()
                ->with(['user:id,name,email', 'patient:id,name,email', 'payments'])
                ->latest('start')
                ->limit(30)
                ->get()
                ->map(fn (Appointment $appointment) => [
                    'id' => $appointment->id,
                    'start' => $appointment->start,
                    'title' => $appointment->title,
                    'state' => $appointment->state,
                    'payment_status' => $appointment->payment_status,
                    'psychologist' => $appointment->getRelation('user')?->name,
                    'patient' => $appointment->getRelation('patient')?->name,
                    'paid_amount' => (float) $appointment->payments
                        ->whereIn('status', ['completed', 'paid', 'succeeded', 'approved'])
                        ->sum('amount'),
                ]),
        ]);
    }

    public function storePayment(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'card', 'deposit', 'other'])],
            'status' => ['required', Rule::in(['completed', 'pending'])],
            'concepto' => ['nullable', 'string', 'max:255'],
            'receipt_url' => ['nullable', 'url', 'max:255'],
        ]);

        $payment = DB::transaction(function () use ($validated, $appointment) {
            $payment = Payment::create([
                ...$validated,
                'user_id' => $appointment->user,
                'patient_id' => $appointment->patient,
                'appointment_id' => $appointment->id,
                'payer_type' => 'patient',
                'currency' => $validated['currency'] ?? 'MXN',
            ]);

            if ($validated['status'] === 'completed') {
                $appointment->forceFill(['payment_status' => 'paid'])->save();
                $appointment->cart?->update(['estado' => 'pagado']);
            }

            return $payment;
        });

        return response()->json([
            'success' => true,
            'message' => 'Pago registrado correctamente.',
            'data' => $payment,
        ], 201);
    }
}
