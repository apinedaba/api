<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\EmailService;
use App\Models\DeviceToken;
use App\Services\Fcm;
class DownloadAppNotification extends Command
{
    protected $signature = 'mindmeet:download-app-notification';
    protected $description = 'Envio de notificaciones push para app';



    public function handle()
    {
        $psychologists = User::whereIn('id', [73, 60, 78])->get();

        foreach ($psychologists as $psy) {
            $tokens = DeviceToken::where('user_id', $psy->id)->pluck('token')->all();
            foreach ($tokens as $token) {
                Fcm::send($token, "Descarga la app", "¡Ahora mindmeet esta en playstore para usuarios android! Descargala para acceder mas rapido a tu cuenta", [
                    'link' => 'https://play.google.com/store/apps/details?id=mx.com.mindmeet.minder.twa',
                    'icon' => 'https://res.cloudinary.com/dabwvv94x/image/upload/v1764639595/android-chrome-192x192_aogrgh.png'
                ]);
            }
        }

        $this->info('Total de psicólogos: ' . $psychologists->count());
    }
}
