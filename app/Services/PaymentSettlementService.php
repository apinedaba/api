<?php

namespace App\Services;

use App\Models\Payment;

class PaymentSettlementService
{
    public function mindmeetFeeRate(): float
    {
        return (float) config('services.checkout.mindmeet_fee_rate', 0.10);
    }

    public function isMindMeetCollected(Payment $payment): bool
    {
        return filled($payment->stripe_payment_id)
            && in_array(strtolower((string) $payment->payment_method), ['card', 'oxxo', 'stripe'], true);
    }

    public function isSessionConcluded(Payment $payment): bool
    {
        if (! $payment->appointment) {
            return false;
        }

        return $this->isCompletedStatus($payment->appointment->statusUser)
            && $this->isCompletedStatus($payment->appointment->statusPatient);
    }

    public function isWithdrawable(Payment $payment): bool
    {
        return $this->isMindMeetCollected($payment)
            && $payment->status === 'completed'
            && $this->isSessionConcluded($payment);
    }

    public function breakdown(Payment $payment): array
    {
        if (! $this->isMindMeetCollected($payment)) {
            $amount = round((float) $payment->amount, 2);

            return [
                'patient_charge_amount' => $amount,
                'session_amount' => $amount,
                'stripe_fee_amount' => 0.0,
                'stripe_fee_is_estimated' => false,
                'mindmeet_fee_rate' => 0.0,
                'mindmeet_fee_amount' => 0.0,
                'net_psychologist_amount' => $amount,
            ];
        }

        $sessionAmount = round((float) ($payment->charge_subtotal_amount
            ?? $payment->session_base_amount
            ?? $payment->amount), 2);
        $patientCharge = round((float) ($payment->total_charge_amount ?? $payment->amount), 2);
        $stripeFee = $payment->stripe_fee_amount !== null
            ? round((float) $payment->stripe_fee_amount, 2)
            : round(max($patientCharge - $sessionAmount, 0), 2);
        $mindmeetFeeRate = $payment->mindmeet_fee_rate !== null
            ? (float) $payment->mindmeet_fee_rate
            : $this->mindmeetFeeRate();
        $mindmeetFee = $payment->mindmeet_fee_amount !== null
            ? round((float) $payment->mindmeet_fee_amount, 2)
            : round($sessionAmount * $mindmeetFeeRate, 2);
        $net = round(max($patientCharge - $stripeFee - $mindmeetFee, 0), 2);

        return [
            'patient_charge_amount' => $patientCharge,
            'session_amount' => $sessionAmount,
            'stripe_fee_amount' => $stripeFee,
            'stripe_fee_is_estimated' => $payment->stripe_fee_amount === null,
            'mindmeet_fee_rate' => $mindmeetFeeRate,
            'mindmeet_fee_amount' => $mindmeetFee,
            'net_psychologist_amount' => $net,
        ];
    }

    public function synchronizeSettlementFields(Payment $payment): Payment
    {
        $breakdown = $this->breakdown($payment);
        $payment->forceFill([
            'mindmeet_fee_rate' => $breakdown['mindmeet_fee_rate'],
            'mindmeet_fee_amount' => $breakdown['mindmeet_fee_amount'],
            'psychologist_amount' => $breakdown['net_psychologist_amount'],
            'payout_status' => $this->isWithdrawable($payment) ? 'available' : 'held',
        ])->save();

        return $payment;
    }

    private function isCompletedStatus(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), [
            'completed',
            'complete',
            'completada',
            'concluida',
            'terminada',
            'finalizada',
        ], true);
    }
}
