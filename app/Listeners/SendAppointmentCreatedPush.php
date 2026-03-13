<?php

namespace App\Listeners;

use App\Events\AppointmentCreated;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\Patient;
use App\Models\Appointment; // ajusta al nombre de tu modelo
use App\Services\Fcm;
use Illuminate\Contracts\Queue\ShouldQueue;
use Log;
use Carbon\Carbon;

class SendAppointmentCreatedPush implements ShouldQueue
{
    public function handle(AppointmentCreated $event): void
    {
        $psy = User::find($event->psychologistId);
        $pat = Patient::find($event->patientId);
        if (!$psy || !$pat)
            return;

        $appt = Appointment::find($event->appointmentId);
        if (!$appt)
            return;

        // Tokens del psicólogo (destinatario principal)
        $tokens = DeviceToken::where('user_id', $psy->id)->pluck('token')->all();
        if (empty($tokens))
            return;

        $title = 'Nueva cita agendada';
        $when = $appt->start
            ? Carbon::parse($appt->start)->timezone(config('app.timezone'))->format('d/M H:i')
            : '';
        $body = "{$pat->name} agendó una cita para {$when}";

        $link = url("/dashboard/psychologist/appointments/{$appt->id}"); // ajusta ruta

        foreach ($tokens as $token) {
            $push = Fcm::send($token, $title, $body, ['link' => $link]);
            logger($push->status());
            if ($push->status() === 400) {
                DeviceToken::delete($token);
                logger("SE elimino un dispotivo puch");
            }
        }
    }
}