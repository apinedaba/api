<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\SessionStartCodeService;
use Illuminate\Console\Command;

class BackfillWebsiteSessionStartCodes extends Command
{
    protected $signature = 'appointments:backfill-start-codes
                            {--dry-run : Cuenta las sesiones sin guardar cambios}';

    protected $description = 'Genera códigos de inicio faltantes únicamente para sesiones creadas desde el website.';

    public function handle(SessionStartCodeService $codes): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $found = 0;
        $updated = 0;

        Appointment::query()
            ->whereHas('cart', fn ($query) => $query->whereRaw('LOWER(source) = ?', ['website']))
            ->where(function ($query) {
                $query->whereNull('session_start_code_hash')
                    ->orWhereNull('session_start_code_encrypted')
                    ->orWhere('session_start_code_hash', '')
                    ->orWhere('session_start_code_encrypted', '');
            })
            ->with('cart')
            ->orderBy('id')
            ->chunkById(100, function ($appointments) use ($codes, $dryRun, &$found, &$updated): void {
                foreach ($appointments as $appointment) {
                    $found++;

                    if ($dryRun || ! $codes->appliesTo($appointment)) {
                        continue;
                    }

                    $codes->issue($appointment);
                    $appointment->saveQuietly();
                    $updated++;
                }
            });

        if ($dryRun) {
            $this->info("Dry run: {$found} sesiones web necesitan código de inicio.");
        } else {
            $this->info("Códigos generados: {$updated}. Sesiones web encontradas: {$found}.");
        }

        return self::SUCCESS;
    }
}
