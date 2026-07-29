<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\ConsultaContacto;
use App\Models\Patient;
use App\Models\PatientUser;
use Illuminate\Console\Command;

class BackfillLeadStatuses extends Command
{
    protected $signature = 'leads:backfill-status {--dry-run : Muestra el resultado sin modificar registros}';

    protected $description = 'Marca leads antiguos como convertidos cuando ya existen como pacientes vinculados al psicologo.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $processed = 0;
        $converted = 0;
        $skipped = 0;

        ConsultaContacto::query()
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhereNotIn('status', [
                        ConsultaContacto::STATUS_CONVERTED,
                        ConsultaContacto::STATUS_DISCARDED,
                    ]);
            })
            ->whereNotNull('email')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunkById(200, function ($leads) use ($dryRun, &$processed, &$converted, &$skipped) {
                foreach ($leads as $lead) {
                    $processed++;

                    $patient = Patient::query()
                        ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $lead->email))])
                        ->first();

                    if (! $patient) {
                        $skipped++;
                        continue;
                    }

                    $hasRelationship = PatientUser::query()
                        ->where('user', $lead->user_id)
                        ->where('patient', $patient->id)
                        ->exists();

                    if (! $hasRelationship) {
                        $skipped++;
                        continue;
                    }

                    $appointment = Appointment::query()
                        ->where('user', $lead->user_id)
                        ->where('patient', $patient->id)
                        ->when($lead->created_at, fn ($query) => $query->where('created_at', '>=', $lead->created_at->copy()->subDay()))
                        ->orderBy('created_at')
                        ->first();

                    $convertedAt = $appointment?->created_at ?: $lead->updated_at ?: now();

                    $this->line(sprintf(
                        '%s lead #%d -> patient #%d%s',
                        $dryRun ? '[dry-run]' : '[update]',
                        $lead->id,
                        $patient->id,
                        $appointment ? " appointment #{$appointment->id}" : ''
                    ));

                    if (! $dryRun) {
                        $lead->update([
                            'status' => ConsultaContacto::STATUS_CONVERTED,
                            'patient_id' => $patient->id,
                            'appointment_id' => $appointment?->id,
                            'viewed_at' => $lead->viewed_at ?: $convertedAt,
                            'contacted_at' => $lead->contacted_at ?: $convertedAt,
                            'converted_at' => $lead->converted_at ?: $convertedAt,
                            'discarded_at' => null,
                        ]);
                    }

                    $converted++;
                }
            });

        $this->info("Leads revisados: {$processed}");
        $this->info("Leads convertibles: {$converted}");
        $this->info("Leads sin match: {$skipped}");

        if ($dryRun) {
            $this->warn('No se modifico ningun registro porque usaste --dry-run.');
        }

        return self::SUCCESS;
    }
}
