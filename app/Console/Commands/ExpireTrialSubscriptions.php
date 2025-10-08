<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription; // 1. Importa tu modelo Subscription
use Illuminate\Support\Facades\Log; // 2. Importa el Log para registrar la actividad
use Carbon\Carbon; // 3. Importa Carbon para manejar fechas

class ExpireTrialSubscriptions extends Command
{

    protected $signature = 'app:expire-trials';

    protected $description = 'Cambia el estado de las suscripciones de prueba que han expirado a "trial_expired"';


    public function handle()
    {
        $this->info('Buscando suscripciones de prueba expiradas...'); // Mensaje para la consola

        $expiredSubscriptions = Subscription::where('stripe_status', 'trial')
            ->where('trial_ends_at', '<', Carbon::now());

        $count = $expiredSubscriptions->count();

        if ($count > 0) {
            $expiredSubscriptions->update(['stripe_status' => 'trial_expired']);

            $message = "Se han expirado {$count} suscripciones de prueba.";

            $this->info($message);
            Log::info($message);
        } else {
            $this->info('No se encontraron suscripciones de prueba para expirar.');
        }

        return 0;
    }
}
