<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\AppointmentDeletionService;
use Illuminate\Console\Command;

class PurgeCanceledAppointments extends Command
{
    protected $signature = 'appointments:purge-canceled {--dry-run : Solo muestra cuantas sesiones se eliminarian} {--force : Ejecuta sin pedir confirmacion}';

    protected $description = 'Elimina definitivamente las sesiones que quedaron marcadas como canceladas.';

    public function handle(AppointmentDeletionService $appointmentDeletionService): int
    {
        $appointments = Appointment::with('cart')
            ->where(function ($query) {
                $query->whereIn('statusUser', ['Cancel', 'Cancelado', 'cancel', 'cancelado', 'cancelada'])
                    ->orWhereIn('statusPatient', ['Cancel', 'Cancelado', 'cancel', 'cancelado', 'cancelada'])
                    ->orWhereIn('state', ['Cancel', 'Cancelado', 'Cancelada', 'cancel', 'cancelado', 'cancelada']);
            })
            ->orderBy('start')
            ->get();

        $count = $appointments->count();

        if ($this->option('dry-run')) {
            $this->info("Se eliminarian {$count} sesiones canceladas.");
            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('No hay sesiones canceladas por eliminar.');
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm("Se eliminaran definitivamente {$count} sesiones canceladas. Continuar?")) {
            $this->warn('Operacion cancelada.');
            return self::SUCCESS;
        }

        $deleted = $appointmentDeletionService->deleteMany($appointments);
        $this->info("Se eliminaron {$deleted} sesiones canceladas.");

        return self::SUCCESS;
    }
}
