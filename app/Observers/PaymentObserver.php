<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\ProfessionalFunnelAnalyticsService;
use Illuminate\Support\Facades\DB;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $this->trackIfCompleted($payment);
    }

    public function updated(Payment $payment): void
    {
        if ($payment->wasChanged('status')) {
            $this->trackIfCompleted($payment);
        }
    }

    private function trackIfCompleted(Payment $payment): void
    {
        if (strtolower((string) $payment->status) !== 'completed') {
            return;
        }

        DB::afterCommit(function () use ($payment) {
            $fresh = $payment->fresh(['appointment']);
            if ($fresh) {
                app(ProfessionalFunnelAnalyticsService::class)->paymentCompleted($fresh);
            }
        });
    }
}
