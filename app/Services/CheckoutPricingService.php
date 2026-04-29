<?php

namespace App\Services;

use App\Models\AppointmentCart;

class CheckoutPricingService
{
    public function getPlatformFeeRate(): float
    {
        return (float) config('services.checkout.platform_fee_rate', 0.06);
    }

    public function normalizeChargeMode(?string $mode): string
    {
        return in_array($mode, ['avg', 'deposit', 'partial'], true) ? 'avg' : 'now';
    }

    public function buildFromCart(AppointmentCart $cart, ?string $mode = null): array
    {
        $chargeMode = $this->normalizeChargeMode($mode);
        $sessionBaseAmount = round((float) ($cart->precio ?? 0), 2);
        $chargeSubtotal = $chargeMode === 'avg'
            ? round($sessionBaseAmount * 0.10, 2)
            : $sessionBaseAmount;

        $platformFeeRate = $this->getPlatformFeeRate();
        $platformFeeAmount = round($chargeSubtotal * $platformFeeRate, 2);
        $totalChargeAmount = round($chargeSubtotal + $platformFeeAmount, 2);
        $remainingBalanceAmount = round(max($sessionBaseAmount - $chargeSubtotal, 0), 2);

        return [
            'charge_mode' => $chargeMode,
            'session_base_amount' => $sessionBaseAmount,
            'charge_subtotal_amount' => $chargeSubtotal,
            'platform_fee_rate' => $platformFeeRate,
            'platform_fee_amount' => $platformFeeAmount,
            'total_charge_amount' => $totalChargeAmount,
            'psychologist_amount' => $chargeSubtotal,
            'remaining_balance_amount' => $remainingBalanceAmount,
            'payout_status' => 'held',
        ];
    }

    public function fillCart(AppointmentCart $cart, ?string $mode = null): AppointmentCart
    {
        $cart->forceFill($this->buildFromCart($cart, $mode));

        return $cart;
    }
}
