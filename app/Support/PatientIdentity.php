<?php

namespace App\Support;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;

class PatientIdentity
{
    public static function normalizeEmail(?string $email): ?string
    {
        $email = is_string($email) ? trim(mb_strtolower($email)) : null;
        return $email !== '' ? $email : null;
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if (!is_string($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            $digits = substr($digits, -10);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, -10);
        }

        return $digits !== '' ? $digits : null;
    }

    public static function resolveIdentifier(?string $identifier): array
    {
        $identifier = trim((string) $identifier);

        return [
            'email' => filter_var($identifier, FILTER_VALIDATE_EMAIL)
                ? self::normalizeEmail($identifier)
                : null,
            'phone' => self::normalizePhone($identifier),
        ];
    }

    public static function queryByEmailOrPhone(?string $email, ?string $phone): Builder
    {
        if (!$email && !$phone) {
            return Patient::query()->whereRaw('1 = 0');
        }

        return Patient::query()->where(function (Builder $query) use ($email, $phone) {
            if ($email) {
                $query->orWhereRaw('LOWER(email) = ?', [$email]);
            }

            if ($phone) {
                $query->orWhere('phone', $phone);
            }
        });
    }

    public static function findByEmailOrPhone(?string $email, ?string $phone): ?Patient
    {
        if (!$email && !$phone) {
            return null;
        }

        return self::queryByEmailOrPhone($email, $phone)->first();
    }

    public static function buildPatientAttributes(array $data): array
    {
        $email = self::normalizeEmail(data_get($data, 'email'));
        $phone = self::normalizePhone(data_get($data, 'phone', data_get($data, 'contacto.telefono')));
        $contacto = array_merge(data_get($data, 'contacto', []) ?: [], [
            'telefono' => $phone,
        ]);

        return [
            'name' => trim((string) data_get($data, 'name')),
            'email' => $email,
            'phone' => $phone,
            'contacto' => $contacto,
        ];
    }
}
