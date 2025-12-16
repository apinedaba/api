<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\EmailService;

class NotifyPsychologists extends Command
{
    protected $signature = 'mindmeet:notify-psychologists';
    protected $description = 'Notifica psicólogos con acciones pendientes';

    public function handle()
    {
        $psychologists = User::where('activo', false)
            ->where('isProfileComplete', true)
            ->where('identity_verification_status', 'pending')
            ->whereIn('id', [73, 60, 43, 2, 78])
            ->get();

        foreach ($psychologists as $psy) {
            EmailService::send(
                $psy->email,
                'Notificación del equipo MindMeet',
                'email.notify-update-profile',
                [
                    'name' => $psy->name,
                    'missingFields' => ['Cédula profesional', 'Foto INE'],
                    'url' => config('app.frontend_url') . '/perfil'
                ]
            );
        }

        $this->info('Total de psicólogos: ' . $psychologists->count());
    }
}
