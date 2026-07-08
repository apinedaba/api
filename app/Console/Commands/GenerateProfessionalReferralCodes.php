<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ProfessionalReferralService;
use Illuminate\Console\Command;

class GenerateProfessionalReferralCodes extends Command
{
    protected $signature = 'referrals:generate-professional-codes {--force : Regenera codigos existentes}';

    protected $description = 'Genera codigos de referidos para psicologos registrados.';

    public function handle(ProfessionalReferralService $referralService): int
    {
        $created = 0;
        $skipped = 0;

        User::query()
            ->select(['id', 'name', 'email'])
            ->with('professionalReferralCode')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($referralService, &$created, &$skipped) {
                foreach ($users as $user) {
                    if ($user->professionalReferralCode && !$this->option('force')) {
                        $skipped++;
                        continue;
                    }

                    if ($user->professionalReferralCode && $this->option('force')) {
                        $user->professionalReferralCode()->delete();
                    }

                    $referralService->ensureCodeFor($user);
                    $created++;
                }
            });

        $this->info("Codigos creados: {$created}");
        $this->line("Codigos existentes omitidos: {$skipped}");

        return self::SUCCESS;
    }
}
