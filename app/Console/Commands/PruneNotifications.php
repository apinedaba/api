<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--days=180}';
    protected $description = 'Elimina notificaciones leídas, auditorías y tokens obsoletos según la política de retención';

    public function handle(): int
    {
        $days = max(30, (int) $this->option('days'));
        $notifications = DB::table('notifications')->whereNotNull('read_at')->where('created_at', '<', now()->subDays($days))->delete();
        $deliveries = NotificationDelivery::where('created_at', '<', now()->subDays($days * 2))->delete();
        $tokens = DeviceToken::where('updated_at', '<', now()->subDays(120))->delete();
        $this->info("Depuración: {$notifications} notificaciones, {$deliveries} entregas y {$tokens} tokens.");
        return self::SUCCESS;
    }
}
