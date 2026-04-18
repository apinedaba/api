<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\EmailService;

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
                $user = $subscription->user;

                if (!$user) {
                    continue;
                }

                try {
                    // 📧 Enviar correo
                    EmailService::send(
                        $user->email,
                        'Tu periodo de prueba ha terminado – MindMeet',
                        'emails.trial-ended',
                        [
                            'name' => $user->name,
                            'url' => config('app.frontend_url') . '/planes'
                        ]
                    );

                    // 🔒 Marcar como expirado (evita reenvíos)
                    $subscription->update([
                        'stripe_status' => 'trial_expired',
                        'updated_at' => now(),
                    ]);

                    Log::info("Trial expirado y notificado: Subscription ID {$subscription->id}");

                } catch (\Throwable $e) {
                    Log::error(
                        "Error procesando subscription {$subscription->id}: " . $e->getMessage()
                    );
                }
            }
        });

        $this->info("🚀 Proceso finalizado. {$total} suscripciones expiradas.");
        Log::info("Proceso de expiración de trials completado.");

        return Command::SUCCESS;
    }

}
