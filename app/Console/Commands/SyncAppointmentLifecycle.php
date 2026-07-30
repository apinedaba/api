<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\PaymentSettlementService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SyncAppointmentLifecycle extends Command
{
    protected $signature = 'appointments:sync-lifecycle';

    protected $description = 'Actualiza citas programadas a en curso o completadas según su horario.';

    public function handle(PaymentSettlementService $settlements): int
    {
        $now = now();
        $completed = 0;

        Appointment::query()
            ->with(['payments', 'payments.appointment'])
            ->where('start', '<=', $now)
            ->where('end', '<=', $now)
            ->whereNotNull('end')
            ->whereIn('lifecycle_status', ['in_process', 'in_progress'])
            ->where(fn (Builder $query) => $query
                ->whereNull('statusUser')
                ->orWhereNotIn('statusUser', $this->cancelledStatuses()))
            ->where(fn (Builder $query) => $query
                ->whereNull('statusPatient')
                ->orWhereNotIn('statusPatient', $this->cancelledStatuses()))
            ->where(fn (Builder $query) => $query
                ->whereNull('state')
                ->orWhereNotIn('state', $this->cancelledStates()))
            ->orderBy('id')
            ->chunkById(100, function ($appointments) use ($settlements, &$completed) {
                foreach ($appointments as $appointment) {
                    DB::transaction(function () use ($appointment, $settlements, &$completed) {
                        $appointment->forceFill([
                            'lifecycle_status' => 'complete',
                            'state' => 'Completada',
                            'completed_at' => $appointment->completed_at ?: $appointment->end,
                        ])->save();

                        foreach ($appointment->payments as $payment) {
                            $settlements->synchronizeSettlementFields(
                                $payment->setRelation('appointment', $appointment)
                            );
                        }

                        $completed++;
                    });
                }
            });

        $this->info("Citas completadas: {$completed}.");

        return self::SUCCESS;
    }

    private function cancelledStatuses(): array
    {
        return ['Cancel', 'Cancelado', 'Cancelada', 'cancel', 'cancelado', 'cancelada', 'canceled', 'cancelled'];
    }

    private function cancelledStates(): array
    {
        return ['Cancel', 'Cancelado', 'Cancelada', 'cancel', 'cancelado', 'cancelada', 'canceled', 'cancelled', 'No asistió', 'no_asistio'];
    }
}
