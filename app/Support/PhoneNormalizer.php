<?php

namespace App\Support;

use InvalidArgumentException;

class PhoneNormalizer
{
    public static function toE164(?string $phone, string $defaultCountryCode = '52'): string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            throw new InvalidArgumentException('El telefono es requerido.');
        }

        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException('El telefono no contiene digitos validos.');
        }

        if (! $hasPlus && strlen($digits) === 10) {
            $digits = $defaultCountryCode.$digits;
        }

        $normalized = '+'.$digits;

        if (! preg_match('/^\+[1-9]\d{7,14}$/', $normalized)) {
            throw new InvalidArgumentException('El telefono debe tener formato E.164 valido.');
        }

        return $normalized;
    }
}
