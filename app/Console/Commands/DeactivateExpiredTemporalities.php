<?php

namespace App\Console\Commands;

use App\Services\TemporalityService;
use Illuminate\Console\Command;

class DeactivateExpiredTemporalities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'temporalities:deactivate-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Desactiva temporalidades programadas que han vencido';

    /**
     * Execute the console command.
     */
    public function handle(TemporalityService $temporalityService): int
    {
        $count = $temporalityService->deactivateExpiredTemporalities();

        if ($count > 0) {
            $this->info("Se desactivaron {$count} temporalidades vencidas.");
        } else {
            $this->info('No hay temporalidades vencidas para desactivar.');
        }

        return Command::SUCCESS;
    }
}
