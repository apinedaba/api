<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use App\Models\AppointmentCart;

class SessionStartCodeService
{
    public function appliesTo(Appointment $appointment): bool
    {
        if (! $appointment->cart_id) {
            return false;
        }

        $source = $appointment->relationLoaded('cart')
            ? $appointment->cart?->source
            : AppointmentCart::whereKey($appointment->cart_id)->value('source');

        return strtolower((string) $source) === 'website';
    }

    public function issue(Appointment $appointment): string
    {
        $code = (string) random_int(100000, 999999);
        $appointment->session_start_code_hash = Hash::make($code);
        $appointment->session_start_code_encrypted = Crypt::encryptString($code);
        $appointment->session_start_code_attempts = 0;

        return $code;
    }

    public function reveal(Appointment $appointment): string
    {
        return Crypt::decryptString((string) $appointment->session_start_code_encrypted);
    }

    public function verify(Appointment $appointment, string $code): bool
    {
        return filled($appointment->session_start_code_hash)
            && Hash::check($code, $appointment->session_start_code_hash);
    }
}
