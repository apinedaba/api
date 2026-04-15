<?php

namespace App\Services;

use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

                if ($appointment->google_event_id && $professional && $professional->googleAccount) {
                    SyncAppointmentToGoogleCalendar::dispatch($appointment, $professional, 'delete');
                }

                $cart = $appointment->cart;
                if ($cart) {
                    $appointment->cart_id = null;
                    $appointment->saveQuietly();
                }

                $appointment->delete();
                $cart?->delete();
            }

            return $appointments->count();
        });
    }
}
