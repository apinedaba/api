<?php

namespace App\Services;

use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppointmentDeletionService
{
    public function deleteMany(Collection $appointments): int
    {
        if ($appointments->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($appointments) {
            foreach ($appointments as $appointment) {
                $appointment->loadMissing('cart');
                $professional = User::find($appointment->user);

                Log::channel(config('logging.default'))->warning('Appointment moved to recoverable deletion.', [
                    'appointment_id' => $appointment->id,
                    'organization_id' => $appointment->organization_id,
                    'professional_id' => $appointment->user,
                    'patient_id' => $appointment->patient,
                    'start' => optional($appointment->start)->toIso8601String(),
                    'status_user' => $appointment->statusUser,
                    'status_patient' => $appointment->statusPatient,
                    'state' => $appointment->state,
                    'actor_id' => auth()->id(),
                ]);

                if ($appointment->google_event_id && $professional && $professional->googleAccount) {
                    SyncAppointmentToGoogleCalendar::dispatch($appointment, $professional, 'delete');
                }

                $appointment->delete();
            }

            return $appointments->count();
        });
    }
}
