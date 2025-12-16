<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Services\TrialReminderService;
use Carbon\Carbon;
class HandleTrialReminders extends Command
{
    protected $signature = 'mindmeet:trial-reminders';
    protected $description = 'Envía recordatorios escalonados de trial';

    public function handle()
    {
        $now = now();

        $subscriptions = Subscription::where('stripe_status', 'trial_expired')
            ->where('user_id', 73)
            ->whereNotNull('trial_ends_at')
            ->with('user')
            ->get();
        \Log::info("Procesando recordatorios para {$subscriptions->count()} suscripciones.");
        foreach ($subscriptions as $subscription) {
            $days = Carbon::parse($subscription->trial_ends_at)->diffInDays($now);
            \Log::info("Procesando recordatorio para {$subscription->user->name} con {$days} días de trial expirado.");
            if ($days >= 1 && is_null($subscription->trial_reminder_day_1_at)) {
                TrialReminderService::sendDay1($subscription);
                continue;
            }

            if ($days >= 3 && is_null($subscription->trial_reminder_day_3_at)) {
                TrialReminderService::sendDay3($subscription);
                continue;
            }

            if ($days >= 7 && is_null($subscription->trial_reminder_day_7_at)) {
                TrialReminderService::sendDay7($subscription);
                continue;
            }
        }

        $this->info('Recordatorios de trial procesados correctamente.');
        return Command::SUCCESS;
    }
}
