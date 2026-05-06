<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SubscriptionBillingNotification;
use Carbon\Carbon;

class SubscriptionBillingNotificationService
{
    public function __construct(
        protected SubscriptionStatusService $subscriptionStatusService
    ) {
    }

    public function notifyUpcomingCharge(User $user, ?array $summary = null): bool
    {
        $summary = $summary ?: $this->subscriptionStatusService->summarize($user);
        $nextPaymentAt = data_get($summary, 'details.next_payment_at');

        if (!$nextPaymentAt) {
            return false;
        }

        $subscription = $user->subscription;
        if (!$subscription) {
            return false;
        }

        $dateKey = Carbon::parse($nextPaymentAt)->toDateString();
        if ($subscription->upcoming_charge_notified_for_date?->toDateString() === $dateKey) {
            return false;
        }

        $user->notify(new SubscriptionBillingNotification('subscription-upcoming-charge', [
            'next_charge_at' => $nextPaymentAt,
            'amount' => data_get($summary, 'details.amount_decimal'),
            'currency' => data_get($summary, 'details.currency', 'MXN'),
        ]));

        $subscription->forceFill([
            'upcoming_charge_notified_for_date' => $dateKey,
        ])->save();

        return true;
    }

    public function notifyFailedCharge(User $user, mixed $invoice = null): bool
    {
        $subscription = $user->subscription;
        $invoiceId = data_get($invoice, 'id');

        if ($subscription && $invoiceId && $subscription->last_payment_failed_invoice_id === $invoiceId) {
            return false;
        }

        $amountDue = data_get($invoice, 'amount_due');
        if (is_numeric($amountDue)) {
            $amountDue = ((float) $amountDue) / 100;
        }

        $currency = strtoupper((string) data_get($invoice, 'currency', 'MXN'));
        $nextRetryAt = data_get($invoice, 'next_payment_attempt')
            ? Carbon::createFromTimestamp(data_get($invoice, 'next_payment_attempt'))->toIso8601String()
            : null;

        $user->notify(new SubscriptionBillingNotification('subscription-charge-failed', [
            'amount' => $amountDue,
            'currency' => $currency,
            'next_retry_at' => $nextRetryAt,
        ]));

        if ($subscription) {
            $subscription->forceFill([
                'last_payment_failed_invoice_id' => $invoiceId,
                'last_payment_failed_notified_at' => now(),
            ])->save();
        }

        return true;
    }
}
