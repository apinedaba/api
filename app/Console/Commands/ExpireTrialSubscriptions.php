<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\EmailService;
use Illuminate\Support\Facades\Cache;

class ExpireTrialSubscriptions extends Command
{

    protected $signature = 'app:expire-trials';

    protected $description = 'Cambia el estado de las suscripciones de prueba que han expirado a "trial_expired"';


    public function handle()
    {
        $this->info('🔍 Buscando suscripciones de prueba expiradas...');

        $now = now();

        $query = Subscription::where('stripe_status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->with('user');

        $total = $query->count();

        if ($total === 0) {
            $this->info('✅ No se encontraron suscripciones de prueba expiradas.');
            Log::info('No hay suscripciones trial expiradas.');
            return Command::SUCCESS;
        }

        $this->info("⚠️ Se encontraron {$total} suscripciones de prueba expiradas.");
        Log::info("Procesando {$total} suscripciones trial expiradas.");

        $query->chunkById(50, function ($subscriptions) {
            foreach ($subscriptions as $subscription) {
                Cache::lock("expire-trial:{$subscription->id}", 300)->get(function () use ($subscription) {
                    $current = $subscription->fresh(['user']);
                    $user = $current?->user;
                    if (! $current || ! $user || $current->stripe_status !== 'trial') return;

                    try {
                        // Reclamar primero evita que dos workers envíen el mismo aviso.
                        $claimed = Subscription::query()
                            ->whereKey($current->id)
                            ->where('stripe_status', 'trial')
                            ->update(['stripe_status' => 'trial_expired']);

                        if ($claimed !== 1) return;

                        EmailService::send(
                            $user->email,
                            'Tu periodo de prueba ha terminado – MindMeet',
                            'emails.trial-ended',
                            [
                                'name' => $user->name,
                                'url' => config('app.frontend_url') . '/planes'
                            ]
                        );

                        Log::info("Trial expirado y notificado: Subscription ID {$current->id}");

                    } catch (\Throwable $e) {
                        Log::error("Error procesando subscription {$current->id}: " . $e->getMessage());
                    }
                });
            }
        });

        $this->info("🚀 Proceso finalizado. {$total} suscripciones expiradas.");
        Log::info("Proceso de expiración de trials completado.");

        return Command::SUCCESS;
    }

}
