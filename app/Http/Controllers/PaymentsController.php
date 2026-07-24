<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Appointment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
                'amount' => ['required', 'numeric', 'gt:0'],
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
                $appointment = Appointment::with(['patient', 'cart', 'payments'])->findOrFail($validated['appointment_id']);
                abort_unless((int) $appointment->user === (int) auth()->id(), 403, 'No puedes registrar pagos en esta sesion.');
            }

            $userId = $appointment?->user ?? auth()->id();
            $patientId = $validated['patient_id'] ?? $appointment?->patient ?? null;

            $validated['user_id'] = $userId;
            $validated['patient_id'] = $patientId;
            $validated['currency'] = $validated['currency'] ?? 'MXN';
            $validated['status'] = $validated['status']
                ?? ((float) $validated['amount'] < 0 ? 'refunded' : 'completed');

            $payment = DB::transaction(function () use ($validated, $appointment) {
                $payment = Payment::create($validated);

                if ($appointment) {
                    $this->syncAppointmentPaymentStatus($appointment->fresh(['cart', 'payments']));
                }

                return $payment;
            });

            $summary = $appointment
                ? $this->appointmentPaymentSummary($appointment->fresh(['cart', 'payments']))
                : null;

            return response()->json([
                'rasson' => 'El pago se registro exitosamente.',
                'message' => "Pago registrado",
                'type' => "success",
                'payment' => $payment,
                'summary' => $summary,
            ], 200);
        } catch (\Throwable $th) {
            if ($th instanceof ValidationException || $th instanceof HttpExceptionInterface) {
                throw $th;
            }

            return response()->json([
                'rasson' => 'Ocurrio un error al registrar el pago: ' . $th->getMessage(),
                'message' => "Pago no registrado",
                'type' => "error",
            ],  400);

        }
    }

    private function syncAppointmentPaymentStatus(Appointment $appointment): void
    {
        $summary = $this->appointmentPaymentSummary($appointment);
        if ($summary['session_amount'] === null) {
            return;
        }

        $paymentStatus = $summary['is_fully_paid'] ? 'paid' : 'pending';
        $appointment->forceFill(['payment_status' => $paymentStatus])->save();

        if ($appointment->cart) {
            $appointment->cart->update([
                'estado' => $summary['is_fully_paid'] ? 'pagado' : 'pendiente',
            ]);
        }
    }

    private function appointmentPaymentSummary(Appointment $appointment): array
    {
        $sessionAmount = is_numeric($appointment->cart?->precio)
            ? round((float) $appointment->cart->precio, 2)
            : null;

        $settledStatuses = ['completed', 'paid', 'succeeded', 'approved'];
        $paidAmount = round(
            $appointment->payments->sum(function (Payment $payment) use ($settledStatuses): float {
                $status = strtolower((string) $payment->status);
                $amount = (float) $payment->amount;

                if ($amount < 0 || $status === 'refunded') {
                    return $amount;
                }

                return in_array($status, $settledStatuses, true) ? $amount : 0;
            }),
            2
        );
        $balance = $sessionAmount === null
            ? null
            : round(max($sessionAmount - $paidAmount, 0), 2);

        return [
            'session_amount' => $sessionAmount,
            'paid_amount' => $paidAmount,
            'balance_amount' => $balance,
            'payment_count' => $appointment->payments->count(),
            'is_fully_paid' => $sessionAmount !== null && $sessionAmount > 0 && $balance <= 0.01,
        ];
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
