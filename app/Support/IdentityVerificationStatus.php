<?php

namespace App\Support;

use InvalidArgumentException;

final class IdentityVerificationStatus
{
    public const PENDING = 'pending';
    public const SENDING = 'sending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    public const ALL = [
        self::PENDING,
        self::SENDING,
        self::APPROVED,
        self::REJECTED,
    ];

    public static function validate(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        if (! in_array($status, self::ALL, true)) {
            throw new InvalidArgumentException('Estado de verificacion de identidad no valido.');
        }

        return $status;
    }
}
