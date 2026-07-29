<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Appointment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\PaymentSettlementService;

class PaymentsController extends Controller
{
    public function __construct(private PaymentSettlementService $settlements)
    {
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        $payments = Payment::where('user_id', $user->id)
            ->with(['appointment', 'patient'])
            ->latest()
            ->get()
            ->map(function (Payment $payment) {
                $breakdown = $this->settlements->breakdown($payment);
                $payment->collected_by_mindmeet = $this->settlements->isMindMeetCollected($payment);
                $payment->session_concluded = $this->settlements->isSessionConcluded($payment);
                $payment->is_withdrawable = $this->settlements->isWithdrawable($payment);
                $payment->gross_amount = $breakdown['patient_charge_amount'];
                $payment->session_amount = $breakdown['session_amount'];
                $payment->stripe_fee_amount = $breakdown['stripe_fee_amount'];
                $payment->stripe_fee_is_estimated = $breakdown['stripe_fee_is_estimated'];
                $payment->mindmeet_fee_rate = $breakdown['mindmeet_fee_rate'];
                $payment->mindmeet_fee_amount = $breakdown['mindmeet_fee_amount'];
                $payment->net_psychologist_amount = $breakdown['net_psychologist_amount'];
                $payment->availability_status = $payment->is_withdrawable ? 'available' : 'held';

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
            'mindmeet_fee_rate' => $this->settlements->mindmeetFeeRate(),
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
