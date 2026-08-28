<?php

namespace App\Services;

use App\Models\Subscription;

class TrialReminderService
{
    public static function sendDay1(Subscription $subscription): void
    {
        if (! self::claim($subscription, 'trial_reminder_day_1_at')) {
            return;
        }

        EmailService::send(
            $subscription->user->email,
            'Tu periodo de prueba ha terminado – MindMeet',
            'email.trial-ended',
            [
                'name' => $subscription->user->name,
                'url' => config('app.frontend_url') . '/planes'
            ]
        );

        \Log::info("Recordatorio de día 1 enviado para {$subscription->user->name}");
    }

    public static function sendDay3(Subscription $subscription): void
    {
        if (! self::claim($subscription, 'trial_reminder_day_3_at')) {
            return;
        }

        EmailService::send(
            $subscription->user->email,
            'Evita la deshabilitación de tu cuenta – MindMeet',
            'email.trial-reminder-urgent',
            [
                'name' => $subscription->user->name,
                'url' => config('app.frontend_url') . '/planes'
            ]
        );

    }

    public static function sendDay7(Subscription $subscription): void
    {
        $claimed = Subscription::query()
            ->whereKey($subscription->id)
            ->whereNull('trial_reminder_day_7_at')
            ->whereNotIn('stripe_status', ['active', 'canceled', 'trial_disabled'])
            ->update([
                'trial_reminder_day_7_at' => now(),
                'stripe_status' => 'trial_disabled',
            ]);

        if ($claimed !== 1) {
            return;
        }

        // La cuenta se desactiva antes del envío para que dos workers nunca
        // puedan reclamar y enviar el mismo aviso final.
        $subscription->user->update(['activo' => false]);

        EmailService::send(
            $subscription->user->email,
            'Último aviso – Tu cuenta será deshabilitada',
            'email.trial-reminder-final',
            [
                'name' => $subscription->user->name,
            ]
        );

    }

    private static function claim(Subscription $subscription, string $column): bool
    {
        return Subscription::query()
            ->whereKey($subscription->id)
            ->whereNull($column)
            ->whereNotIn('stripe_status', ['active', 'canceled', 'trial_disabled'])
            ->update([$column => now()]) === 1;
    }
}
