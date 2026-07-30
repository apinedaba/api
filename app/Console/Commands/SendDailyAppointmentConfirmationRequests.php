<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\WhatsApp\AppointmentWhatsAppNotifier;
use Illuminate\Console\Command;

class SendDailyAppointmentConfirmationRequests extends Command
{
    protected $signature = 'appointments:request-confirmation {--dry-run}';
    protected $description = 'Solicita una vez la confirmacion de citas no confirmadas del dia';

    public function handle(AppointmentWhatsAppNotifier $notifier): int
    {
        $date = now(config('app.timezone'))->toDateString();
        $appointments = Appointment::query()
            ->with(['patient', 'user'])
            ->whereDate('start', $date)
            ->where(fn ($query) => $query->whereNull('statusPatient')->orWhereNotIn('statusPatient', ['Confirmed', 'Cancel']))
            ->where(fn ($query) => $query->whereNull('state')->orWhereNotIn('state', ['Cancelada', 'Cancelado']))
            ->get();

        $sent = 0;
        foreach ($appointments as $appointment) {
            $patientStatus = mb_strtolower(trim((string) $appointment->statusPatient));
            $state = mb_strtolower(trim((string) $appointment->state));
            if (in_array($patientStatus, ['confirmed', 'confirmada', 'confirmado', 'cancel', 'cancelada', 'cancelado'], true)
                || in_array($state, ['cancel', 'cancelada', 'cancelado'], true)) continue;
            if (data_get($appointment->notification_meta, 'confirmation_request.sent_date') === $date) continue;
            if ($this->option('dry-run')) {
                $this->line("Cita {$appointment->id}");
                continue;
            }
            if ($notifier->appointmentReminder($appointment, 'scheduler.daily-confirmation')) {
                $meta = $appointment->notification_meta ?? [];
                data_set($meta, 'confirmation_request.sent_date', $date);
                $appointment->forceFill(['notification_meta' => $meta])->save();
                $sent++;
            }
        }
        $this->info("Solicitudes enviadas: {$sent}");
        return self::SUCCESS;
    }
}
