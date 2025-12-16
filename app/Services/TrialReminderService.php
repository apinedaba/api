<?php

namespace App\Services;

use App\Models\Subscription;

class TrialReminderService
{
    public static function sendDay1(Subscription $subscription): void
    {
        EmailService::send(
            $subscription->user->email,
            'Tu periodo de prueba ha terminado – MindMeet',
            'email.trial-ended',
            [
                'name' => $subscription->user->name,
                'url' => config('app.frontend_url') . '/planes'
            ]
        );

        $subscription->update([
            'trial_reminder_day_1_at' => now(),
        ]);
        \Log::info("Recordatorio de día 1 enviado para {$subscription->user->name}");
    }

    public static function sendDay3(Subscription $subscription): void
    {
        EmailService::send(
            $subscription->user->email,
            'Evita la deshabilitación de tu cuenta – MindMeet',
            'email.trial-reminder-urgent',
            [
                'name' => $subscription->user->name,
                'url' => config('app.frontend_url') . '/planes'
            ]
        );

        $subscription->update([
            'trial_reminder_day_3_at' => now(),
        ]);
    }

    public static function sendDay7(Subscription $subscription): void
    {
        EmailService::send(
            $subscription->user->email,
            'Último aviso – Tu cuenta será deshabilitada',
            'email.trial-reminder-final',
            [
                'name' => $subscription->user->name,
            ]
        );

        $subscription->update([
            'trial_reminder_day_7_at' => now(),
            'stripe_status' => 'trial_disabled',
        ]);

        // 🔒 Aquí puedes deshabilitar la cuenta
        $subscription->user->update([
            'is_active' => false,
        ]);
    }
}