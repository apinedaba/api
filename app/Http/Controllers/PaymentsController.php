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
        $payments = Payment::where('user_id', $user->id)
            ->with(['appointment', 'patient'])
            ->get()
            ->map(function (Payment $payment) {
                $payment->collected_by_mindmeet = $this->isMindMeetCollectedPayment($payment);
                $payment->is_withdrawable = $payment->collected_by_mindmeet && $payment->status === 'completed';
                $payment->gross_amount = round((float) $payment->amount, 2);
                $payment->mindmeet_fee_amount = $this->mindmeetFeeAmount($payment);
                $payment->net_psychologist_amount = $this->netPsychologistAmount($payment);

                return $payment;
            });

        return response()->json([
            'payments'=> $payments,
            'total' => round($payments->sum('net_psychologist_amount'), 2),
            'gross_total' => round($payments->sum('gross_amount'), 2),
            'mindmeet_fee_total' => round($payments->sum('mindmeet_fee_amount'), 2),
            'net_total' => round($payments->sum('net_psychologist_amount'), 2),
            'withdrawable_total' => round($payments->where('is_withdrawable', true)->sum('net_psychologist_amount'), 2),
            'manual_total' => round($payments->where('collected_by_mindmeet', false)->sum('net_psychologist_amount'), 2),
            'platform_fee_rate' => (float) config('services.checkout.platform_fee_rate', 0.06),
        ], 200);

    }

    private function netPsychologistAmount(Payment $payment): float
    {
        if ($payment->psychologist_amount !== null) {
            return round((float) $payment->psychologist_amount, 2);
        }

        if ($payment->platform_fee_amount !== null) {
            return round(max((float) $payment->amount - (float) $payment->platform_fee_amount, 0), 2);
        }

        if ($this->isMindMeetCollectedPayment($payment)) {
            $feeRate = (float) config('services.checkout.platform_fee_rate', 0.06);

            return round(((float) $payment->amount) / (1 + $feeRate), 2);
        }

        return round((float) $payment->amount, 2);
    }

    private function mindmeetFeeAmount(Payment $payment): float
    {
        if ($payment->platform_fee_amount !== null) {
            return round((float) $payment->platform_fee_amount, 2);
        }

        return round(max((float) $payment->amount - $this->netPsychologistAmount($payment), 0), 2);
    }

    private function isMindMeetCollectedPayment(Payment $payment): bool
    {
        return filled($payment->stripe_payment_id)
            && in_array(strtolower((string) $payment->payment_method), ['card', 'oxxo', 'stripe'], true);
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
