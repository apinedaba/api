<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SubscriptionBillingNotificationService;
use App\Services\SubscriptionStatusService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyUpcomingSubscriptionCharges extends Command
{
    protected $signature = 'subscriptions:notify-upcoming-charges';

    protected $description = 'Envia recordatorios previos de cobro para suscripciones proximas a renovarse.';

    public function handle(
        SubscriptionStatusService $subscriptionStatusService,
        SubscriptionBillingNotificationService $notificationService
    ): int {
        $count = 0;
        $today = now()->startOfDay();
        $limit = now()->addDays(3)->endOfDay();

        User::query()
            ->whereHas('subscription', function ($query) {
                $query->whereIn('stripe_status', ['active', 'trialing', 'past_due']);
            })
            ->with('subscription')
            ->chunkById(100, function ($users) use ($subscriptionStatusService, $notificationService, $today, $limit, &$count) {
                foreach ($users as $user) {
                    $summary = $subscriptionStatusService->summarize($user);
                    $nextPaymentAt = data_get($summary, 'details.next_payment_at');

                    if (!$nextPaymentAt) {
                        continue;
                    }

                    $nextPaymentDate = Carbon::parse($nextPaymentAt);
                    if ($nextPaymentDate->lt($today) || $nextPaymentDate->gt($limit)) {
                        continue;
                    }

                    if ($notificationService->notifyUpcomingCharge($user, $summary)) {
                        $count++;
                    }
                }
            });

        $this->info("Recordatorios enviados: {$count}");

        return self::SUCCESS;
    }
}
