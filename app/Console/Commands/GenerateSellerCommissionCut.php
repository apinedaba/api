<?php

namespace App\Console\Commands;

use App\Services\SellerCommissionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateSellerCommissionCut extends Command
{
    protected $signature = 'sellers:generate-commission-cut {--date= : Fecha del corte en formato YYYY-MM-DD}';

    protected $description = 'Genera de forma idempotente el corte mensual de comisiones de vendedores.';

    public function handle(SellerCommissionService $service): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now();

        $created = $service->generateCut($date);

        $this->info("Corte generado. Nuevas comisiones: {$created->count()}");

        return self::SUCCESS;
    }
}
