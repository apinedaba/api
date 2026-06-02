<?php

namespace App\Console\Commands;

use App\Services\MarketingPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryFailedMarketingWebhooks extends Command
{
    protected $signature = 'marketing:retry-webhooks {--force : Ignorar el schedule y ejecutar ahora}';
    protected $description = 'Reintenta procesar webhooks de MindBoost que fallaron anteriormente';

    public function handle()
    {
        $this->info('🔄 Reiniciando webhooks fallidos de MindBoost...');

        try {
            MarketingPaymentService::retryFailedWebhooks();
            $this->info('✅ Retry completado exitosamente.');
        } catch (\Exception $e) {
            $this->error('❌ Error durante retry: ' . $e->getMessage());
            Log::error('Error en retry de webhooks', ['error' => $e->getMessage()]);
            return 1;
        }

        return 0;
    }
}
