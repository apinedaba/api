<?php

namespace Tests\Unit;

use App\Models\User;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdentityVerificationStatusTest extends TestCase
{
    public static function validStatuses(): array
    {
        return [
            ['pending'],
            ['sending'],
            ['approved'],
            ['rejected'],
        ];
    }

    #[DataProvider('validStatuses')]
    public function test_model_accepts_supported_statuses(string $status): void
    {
        $user = new User();
        $user->identity_verification_status = $status;

        $this->assertSame($status, $user->identity_verification_status);
    }

    public function test_model_rejects_an_unknown_status(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $user = new User();
        $user->identity_verification_status = 'unknown';
    }
}
