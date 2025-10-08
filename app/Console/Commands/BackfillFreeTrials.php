<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BackfillFreeTrials extends Command
{
    protected $signature = 'app:backfill-trials';
    protected $description = 'Otorga una prueba gratuita de 15 días a los usuarios que se registraron sin una y no tienen acceso vitalicio.';

    public function handle()
    {
        $this->info('Iniciando proceso para asignar pruebas gratuitas retroactivas...');
        Log::info('Iniciando BackfillFreeTrials...');

        // Buscamos usuarios que:
        // 1. NO TIENEN una suscripción asociada.
        // 2. Y que NO TIENEN la bandera de acceso vitalicio.
        $usersWithoutSubscription = User::whereDoesntHave('subscription')
            ->where('has_lifetime_access', false) // Condición mejorada
            // Opcional: Filtro por fecha.
            ->whereDate('created_at', '>=', Carbon::parse('2025-09-30')->startOfDay())
            ->get();

        if ($usersWithoutSubscription->isEmpty()) {
            $this->info('No se encontraron usuarios que necesiten una prueba gratuita.');
            return 0;
        }

        $this->info("Se encontraron {$usersWithoutSubscription->count()} usuarios para procesar.");

        $progressBar = $this->output->createProgressBar($usersWithoutSubscription->count());
        $progressBar->start();

        foreach ($usersWithoutSubscription as $user) {
            Subscription::create([
                'user_id' => $user->id,
                'stripe_status' => 'trial',
                'trial_ends_at' => Carbon::now()->addDays(15),
            ]);

            Log::info("Prueba gratuita creada para el usuario: {$user->email} (ID: {$user->id})");
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\n¡Proceso completado! Se asignaron pruebas gratuitas a {$usersWithoutSubscription->count()} usuarios.");
        Log::info('Finalizó BackfillFreeTrials.');

        return 0;
    }
}
