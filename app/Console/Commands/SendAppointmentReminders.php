<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;
use App\Models\User;
use App\Services\EmailService;
use App\Services\Fcm;
use App\Models\DeviceToken;
use Log;


class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    public function handle()
    {
        $now = now();
        $target = now()->addMinutes(30);
        $this->info('Fecha actual: ' . $now);
        $this->info('Fecha objetivo: ' . $target);
        $appointments = Appointment::whereBetween(
            'start',
            [$now, $target]
        );

        foreach ($appointments->get() as $appointment) {
            $this->info('' . $appointment);
            $this->info('Total de citas: ' . $appointment->patient()->name . '' . $appointment->patient()->email . '');
            $psychologist = $appointment->user;
            $patientName = $appointment->patient()->name;
            $patientEmail = $appointment->patient()->email;

            $tokens = DeviceToken::where('user_id', $psychologist->id)->pluck('token')->all();
            Log::info(count($tokens));
            foreach ($tokens as $token) {
                Fcm::send($token, "Tu sesión comienza pronto", "Tu sesión con {$patientName} comienza en 30 minutos, ¡prepárate!", [
                    'link' => config('app.frontend_url') . '/perfil',
                    'icon' => 'https://res.cloudinary.com/dabwvv94x/image/upload/v1764639595/android-chrome-192x192_aogrgh.png'
                ]);
            }
        }
    }
}
