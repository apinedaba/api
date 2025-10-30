<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use App\Models\User;
use App\Models\DeviceToken;
use App\Services\Fcm;
use Carbon\Carbon;

class SendDailySessionSummary extends Command
{
    protected $signature = 'sessions:daily-summary';
    protected $description = 'Enviar resumen diario de sesiones a los psicólogos';

    public function handle()
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        // 🔎 Obtiene los psicólogos activos
        $psychologists = User::where('activo', true)->where('isProfileComplete', true)->get();
        $psychologistsCount = $psychologists->count();
        $totalSent = 0;

        foreach ($psychologists as $psy) {
            \Log::alert("Si hay psicologos");
            // Cuenta sus sesiones del día
            $sessionCount = Appointment::where('user', $psy->id)
                ->whereBetween('start', [$today, $tomorrow])
                ->count();
            \Log::alert("Hay sesiones:".$sessionCount." de ".$psy->name);
            if ($sessionCount === 0) continue; // no tiene sesiones

            // Busca tokens activos
            $tokens = DeviceToken::where('user_id', $psy->id)->pluck('token')->all();
            if (empty($tokens)) continue;

            $title = "🌞 ¡Buen día, {$psy->name}!";
            $body = "Hoy tienes {$sessionCount} sesión" . ($sessionCount > 1 ? 'es' : '') . ". ¡Prepárate y ten un lindo día!";

            foreach ($tokens as $token) {
                try {
                    Fcm::send($token, $title, $body, [
                        'type' => 'daily_summary',
                        'link' => 'https://admin.mindmeet.com.mx/dashboard',
                        'icon' => 'https://mindmeet.com.mx/assets/icon.png',
                    ]);
                    $totalSent++;
                } catch (\Throwable $e) {
                    \Log::error("Error enviando notificación diaria: " . $e->getMessage());
                }
            }
        }

        $this->info("✅ Notificaciones diarias enviadas: {$totalSent}");
    }
}
