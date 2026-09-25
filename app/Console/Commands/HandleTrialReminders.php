<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Services\TrialReminderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
class HandleTrialReminders extends Command
{
    protected $signature = 'mindmeet:trial-reminders';
    protected $description = 'Envía recordatorios escalonados de trial';

    public function handle()
    {
        $now = now();
        $subscriptions = Subscription::whereIn('stripe_status', ['trial_expired', 'trial', 'trialing'])
            ->whereNotNull('trial_ends_at')
            ->with('user')
            ->get();
        \Log::info("Procesando recordatorios para {$subscriptions->count()} suscripciones.");
        foreach ($subscriptions as $subscription) {
            $trialEndedAt = Carbon::parse($subscription->trial_ends_at);
            if ($trialEndedAt->isFuture()) continue;
            $days = (int) floor($trialEndedAt->diffInHours($now) / 24);

            Cache::lock("trial-reminder:{$subscription->id}", 300)->get(function () use ($subscription, $days) {
                $current = $subscription->fresh(['user']);
                if (! $current || in_array($current->stripe_status, ['active', 'canceled', 'trial_disabled'], true)) return;

                \Log::info("Procesando recordatorio para {$current->user->name} con {$days} días de trial expirado.");
                if ($days >= 7 && is_null($current->trial_reminder_day_7_at)) {
                    TrialReminderService::sendDay7($current);
                    return;
                }
                if ($days >= 3 && is_null($current->trial_reminder_day_3_at)) {
                    TrialReminderService::sendDay3($current);
                    return;
                }
                if ($days >= 1 && is_null($current->trial_reminder_day_1_at)) {
                    TrialReminderService::sendDay1($current);
                }
            });
        }

        $this->info('Recordatorios de trial procesados correctamente.');
        return Command::SUCCESS;
    }
}
