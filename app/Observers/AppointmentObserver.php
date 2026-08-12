<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Notifications\SessionStartCodeNotification;
use App\Services\SessionStartCodeService;
use App\Services\ProfessionalFunnelAnalyticsService;
use App\Services\WhatsApp\AppointmentWhatsAppNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppointmentObserver
{
    public function creating(Appointment $appointment): void
    {
        $codes = app(SessionStartCodeService::class);
        if ($codes->appliesTo($appointment) && ! filled($appointment->session_start_code_hash)) {
            $codes->issue($appointment);
        }
    }

    public function created(Appointment $appointment): void
    {
        DB::afterCommit(function () use ($appointment) {
            $freshAppointment = Appointment::with(['patient', 'user'])->find($appointment->id);
            if ($freshAppointment) {
                app(ProfessionalFunnelAnalyticsService::class)->appointmentBooked($freshAppointment);
            }
            $patient = $freshAppointment?->patient()->first();

            if (! $freshAppointment
                || ! app(SessionStartCodeService::class)->appliesTo($freshAppointment)
                || ! $patient) {
                return;
            }

            try {
                $patient->notify(new SessionStartCodeNotification($freshAppointment));
            } catch (\Throwable $exception) {
                Log::warning('Session start code email notification failed', [
                    'appointment_id' => $appointment->id,
                    'message' => $exception->getMessage(),
                ]);
            }

            app(AppointmentWhatsAppNotifier::class)
                ->sessionStartCode($freshAppointment, 'appointment.created');
        });
    }

    public function updated(Appointment $appointment): void
    {
        if (! $appointment->wasChanged(['payment_status', 'lifecycle_status', 'statusUser', 'completed_at'])) {
            return;
        }

        DB::afterCommit(function () use ($appointment) {
            $fresh = $appointment->fresh();
            if (! $fresh) {
                return;
            }

            $analytics = app(ProfessionalFunnelAnalyticsService::class);
            if (strtolower((string) $fresh->payment_status) === 'paid') {
                $analytics->appointmentPaid($fresh);
            }
            if ($fresh->isProfessionallyCompleted() || $fresh->completed_at) {
                $analytics->appointmentCompleted($fresh);
            }
        });
    }
}
