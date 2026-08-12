<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Payment;
use App\Services\ProfessionalFunnelAnalyticsService;
use Illuminate\Console\Command;

class BackfillProfessionalFunnelAnalytics extends Command
{
    protected $signature = 'analytics:backfill-funnel {--from= : Fecha inicial YYYY-MM-DD}';

    protected $description = 'Reconstruye los hitos históricos del embudo profesional sin duplicar eventos';

    public function handle(ProfessionalFunnelAnalyticsService $analytics): int
    {
        $appointments = Appointment::query()
            ->when($this->option('from'), fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->orderBy('id');

        $total = (clone $appointments)->count();
        $bar = $this->output->createProgressBar($total);

        $appointments->chunkById(200, function ($rows) use ($analytics, $bar) {
            foreach ($rows as $appointment) {
                $analytics->appointmentBooked($appointment);
                if (strtolower((string) $appointment->payment_status) === 'paid') {
                    $analytics->appointmentPaid($appointment);
                }
                if ($appointment->isProfessionallyCompleted() || $appointment->completed_at) {
                    $analytics->appointmentCompleted($appointment);
                }
                $bar->advance();
            }
        });

        Payment::query()
            ->where('status', 'completed')
            ->whereNotNull('appointment_id')
            ->with('appointment')
            ->chunkById(200, function ($payments) use ($analytics) {
                foreach ($payments as $payment) {
                    $analytics->paymentCompleted($payment);
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info('Embudo histórico reconstruido. No se modificaron usuarios, citas ni pagos.');

        return self::SUCCESS;
    }
}
