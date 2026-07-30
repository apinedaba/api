<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Appointment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use App\Services\PaymentSettlementService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            'platform_fee_rate' => (float) config('services.checkout.platform_fee_rate', 0.06),
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
