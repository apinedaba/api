<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdminVendedoresClient;
use Illuminate\Console\Command;

class SyncRecoveryPortfolio extends Command
{
    protected $signature = 'recoveries:sync {--limit=1000 : Máximo de cuentas a sincronizar}';
    protected $description = 'Sincroniza al CRM los psicólogos elegibles para recuperación, sin datos clínicos.';

    public function handle(AdminVendedoresClient $client): int
    {
        $synced = 0;
        User::query()
            ->whereIn('identity_verification_status', ['pending', 'sending'])
            ->pluck('id')
            ->each(fn (int $id) => $client->excludeRecovery($id));

        User::query()
            ->select(['id', 'name', 'email', 'contacto', 'created_at'])
            ->where(fn ($query) => $query->where('has_lifetime_access', false)->orWhereNull('has_lifetime_access'))
            ->where('created_at', '<=', now()->subDays(7))
            ->whereNotIn('identity_verification_status', ['pending', 'sending'])
            ->whereDoesntHave('subscription', fn ($query) => $query->where('stripe_status', 'active'))
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->each(function (User $user) use ($client, &$synced) {
                if (blank($user->email)) {
                    return;
                }

                $client->syncRecovery([
                    'mindmeet_user_id' => $user->id,
                    'nombre' => $user->name,
                    'email' => $user->email,
                    'telefono' => data_get($user->contacto, 'telefono'),
                    'registrado_en' => optional($user->created_at)->toIso8601String(),
                ]);
                $synced++;
            });

        $this->info("Recuperaciones sincronizadas: {$synced}");
        return self::SUCCESS;
    }
}
