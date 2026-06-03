<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Appointment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        $payments = Payment::where('user_id', $user->id)->with(['appointment', 'patient']);
        return response()->json([
            'payments'=> $payments->get(),
            'total' => $payments->sum('amount')
        ], 200);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'payer_type' => ['required', Rule::in(['patient', 'minder'])],
                'appointment_id' => ['nullable', 'exists:appointments,id'],
                'patient_id' => ['nullable', 'exists:patients,id'],
                'amount' => ['required', 'numeric'],
                'currency' => ['nullable', 'string', 'max:10'],
                'payment_method' => ['required', 'string', 'max:255'],
                'status' => ['nullable', 'string', 'max:255'],
                'concepto' => ['nullable', 'string', 'max:255'],
                'id_transaccion_reembolsada' => ['nullable', 'exists:payments,id'],
                'stripe_payment_id' => ['nullable', 'string', 'max:255'],
                'receipt_url' => ['nullable', 'string', 'max:255'],
            ]);

            $appointment = null;
            if (!empty($validated['appointment_id'])) {
                $appointment = Appointment::with(['patient', 'user'])->findOrFail($validated['appointment_id']);
            }

            $userId = $appointment?->user ?? auth()->id();
            $patientId = $validated['patient_id'] ?? $appointment?->patient ?? null;

            $validated['user_id'] = $userId;
            $validated['patient_id'] = $patientId;
            $validated['currency'] = $validated['currency'] ?? 'MXN';
            $validated['status'] = $validated['status']
                ?? ((float) $validated['amount'] < 0 ? 'refunded' : 'completed');

            $payment = Payment::create($validated);
            return response()->json([
                'rasson' => 'El pago se registro exitosamente.',
                'message' => "Pago registrado",
                'type' => "success",
                'payment' => $payment
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'rasson' => 'Ocurrio un error al registrar el pago: ' . $th->getMessage(),
                'message' => "Pago no registrado",
                'type' => "error",
            ],  400);

        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Payment $payments)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Payment $payments)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payments)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payments)
    {
        //
    }
}
