<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;

class CancelExpiredUnconfirmedAppointments extends Command
{
    protected $signature = 'appointments:cancel-expired-unconfirmed {--dry-run}';
    protected $description = 'Cancela citas vencidas que el paciente no confirmo';

    public function handle(): int
    {
        $now = now(config('app.timezone'));
        $updated = 0;

        Appointment::query()
            ->where(fn ($query) => $query->where('end', '<', $now)->orWhere(fn ($fallback) => $fallback->whereNull('end')->where('start', '<', $now)))
            ->where(fn ($query) => $query->whereNull('statusUser')->orWhereNotIn('statusUser', ['Completed', 'Complete', 'Completada', 'Completado', 'Concluida', 'Terminada', 'Finalizada']))
            ->where(fn ($query) => $query->whereNull('lifecycle_status')->orWhereNotIn('lifecycle_status', ['complete', 'completed']))
            ->where(fn ($query) => $query->whereNull('statusPatient')->orWhereNotIn('statusPatient', ['Confirmed', 'Confirmada', 'Confirmado', 'Completed', 'Completada', 'Cancel', 'Cancelada', 'Cancelado']))
            ->where(fn ($query) => $query->whereNull('state')->orWhereNotIn('state', ['Completed', 'Completada', 'Cancel', 'Cancelada', 'Cancelado']))
            ->orderBy('id')
            ->chunkById(200, function ($appointments) use ($now, &$updated) {
                foreach ($appointments as $appointment) {
                    if ($appointment->isProfessionallyCompleted()) {
                        continue;
                    }

                    $patientStatus = mb_strtolower(trim((string) $appointment->statusPatient));
                    $state = mb_strtolower(trim((string) $appointment->state));
                    if (in_array($patientStatus, ['confirmed', 'confirmada', 'confirmado', 'completed', 'completada', 'completado', 'cancel', 'cancelada', 'cancelado'], true)
                        || in_array($state, ['completed', 'completada', 'completado', 'cancel', 'cancelada', 'cancelado'], true)) {
                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line("Cita {$appointment->id}: se cancelaria");
                        continue;
                    }

                    $meta = $appointment->notification_meta ?? [];
                    data_set($meta, 'automatic_cancellation.reason', 'expired_without_patient_confirmation');
                    data_set($meta, 'automatic_cancellation.cancelled_at', $now->toIso8601String());

                    $appointment->forceFill([
                        'statusUser' => 'Cancel',
                        'statusPatient' => 'Cancel',
                        'state' => 'Cancelada',
                        'notification_meta' => $meta,
                    ])->save();
                    $updated++;
                }
            });

        $this->info($this->option('dry-run')
            ? 'Simulacion finalizada; no se modificaron citas.'
            : "Citas canceladas automaticamente: {$updated}");

        return self::SUCCESS;
    }
}
