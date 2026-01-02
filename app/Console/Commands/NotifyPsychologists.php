<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\EmailService;
use App\Services\Fcm;
use App\Models\DeviceToken;
class NotifyPsychologists extends Command
{
    protected $signature = 'mindmeet:notify-psychologists';
    protected $description = 'Notifica psicólogos con acciones pendientes';

    public function handle()
    {
        $psychologists = User::where('activo', true)
            ->where('isProfileComplete', true)
            ->where('identity_verification_status', '!=', 'completed')
            ->where(function ($query) {
                $query->where('has_lifetime_access', true)
                    ->orWhereHas('subscription', function ($q) {
                        $q->where('stripe_status', 'active');
                        $q->orWhere('stripe_status', 'trial');
                    });
            })
            ->get();

        foreach ($psychologists as $psy) {
            EmailService::send(
                $psy->email,
                'Notificación del equipo MindMeet',
                'email.notify-update-profile',
                [
                    'name' => $psy->name,
                    'missingFields' => ['Foto cédula profesional', 'Foto INE'],
                    'url' => config('app.frontend_url') . '/perfil/profesional'
                ]
            );
            $tokens = DeviceToken::where('user_id', $psy->id)->pluck('token')->all();
            foreach ($tokens as $token) {
                Fcm::send($token, "Notificación del equipo MindMeet", "Tienes acciones pendientes, por favor actualiza tu perfil", [
                    'link' => config('app.frontend_url') . '/perfil/profesional',
                    'icon' => 'https://res.cloudinary.com/dabwvv94x/image/upload/v1764639595/android-chrome-192x192_aogrgh.png'
                ]);
            }
        }
        $this->info('Total de psicólogos: ' . $psychologists->count());
    }
}
