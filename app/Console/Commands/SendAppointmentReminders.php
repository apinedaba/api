<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\AppointmentReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Envia recordatorios de citas a pacientes y profesionales';

    public function handle(): int
    {
        $now = now()->timezone(config('app.timezone'));
        $windows = [
            ['key' => '30m', 'minutes' => 30],
        ];

        $appointments = Appointment::with(['patient', 'user'])
            ->whereNotIn('statusUser', ['Cancel', 'Completed'])
            ->whereNotIn('statusPatient', ['Cancel', 'Completed'])
            ->where('start', '>=', $now)
            ->where('start', '<=', $now->copy()->addMinutes(40))
            ->orderBy('start')
            ->get();

        foreach ($appointments as $appointment) {
            foreach ($windows as $window) {
                if (!$this->shouldSendReminder($appointment, $window['minutes'], $window['key'], $now)) {
                    continue;
                }

                if ($appointment->patient) {
                    $appointment->patient->notify(new AppointmentReminderNotification($appointment, $window['key']));
                    $this->markReminderSent($appointment, 'patient', $window['key'], $now);
                }

                if ($appointment->user) {
                    $appointment->user->notify(new AppointmentReminderNotification($appointment, $window['key']));
                    $this->markReminderSent($appointment, 'professional', $window['key'], $now);
                }
            }
        }

        $this->info('Recordatorios de citas procesados correctamente.');

        return self::SUCCESS;
    }

    protected function shouldSendReminder(Appointment $appointment, int $minutesBefore, string $key, Carbon $now): bool
    {
        $targetStart = $now->copy()->addMinutes($minutesBefore);
        $windowEnd = $targetStart->copy()->addMinutes(4);
        $start = Carbon::parse($appointment->start)->timezone(config('app.timezone'));

        if (!$start->betweenIncluded($targetStart, $windowEnd)) {
            return false;
        }

        $meta = $appointment->notification_meta ?? [];

        return !data_get($meta, "{$key}.patient") || !data_get($meta, "{$key}.professional");
    }

    protected function markReminderSent(Appointment $appointment, string $recipient, string $key, Carbon $now): void
    {
        $meta = $appointment->notification_meta ?? [];
        data_set($meta, "{$key}.{$recipient}", $now->toIso8601String());
        $appointment->notification_meta = $meta;
        $appointment->save();
    }
}
