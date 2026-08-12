<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SyncProfessionalOperationalStatus extends Command
{
    protected $signature = 'professionals:sync-operational-status {--dry-run}';

    protected $description = 'Recalcula el estado activo usando telefono, perfil, especialidades, horarios y servicios';

    public function handle(): int
    {
        $changed = 0;
        $activated = 0;
        $deactivated = 0;
        $phonesCopied = 0;

        User::query()->orderBy('id')->chunkById(200, function ($users) use (&$changed, &$activated, &$deactivated, &$phonesCopied) {
            foreach ($users as $user) {
                if ($user->syncPhoneFromWhatsapp(! $this->option('dry-run'))) {
                    $phonesCopied++;
                }

                $expected = $user->hasOperationalSetup();

                if ((bool) $user->activo === $expected) {
                    continue;
                }

                $changed++;
                $expected ? $activated++ : $deactivated++;

                if (! $this->option('dry-run')) {
                    $user->syncOperationalStatus();
                }
            }
        });

        $mode = $this->option('dry-run') ? 'simulados' : 'aplicados';
        $this->info("Cambios {$mode}: {$changed}; telefonos copiados desde WhatsApp: {$phonesCopied}; activados: {$activated}; desactivados: {$deactivated}.");

        return self::SUCCESS;
    }
}
