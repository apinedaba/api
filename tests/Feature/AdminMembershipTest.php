<?php

namespace Tests\Feature;

use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminMembershipTest extends TestCase
{
    use DatabaseTransactions;

    public function test_assigning_content_creator_membership_does_not_complete_profile(): void
    {
        $user = User::factory()->create([
            'isProfileComplete' => false,
            'identity_verification_status' => 'pending',
            'email_verified_at' => null,
            'has_lifetime_access' => false,
            'membership_type' => null,
        ]);

        $request = Request::create('/membership', 'PATCH', [
            'membership_type' => 'content_creator',
        ]);

        app(UserController::class)->updateMembership($request, (string) $user->id);

        $user->refresh();
        $this->assertTrue($user->has_lifetime_access);
        $this->assertSame('content_creator', $user->membership_type);
        $this->assertFalse($user->isProfileComplete);
        $this->assertSame('pending', $user->identity_verification_status);
        $this->assertNull($user->email_verified_at);
    }

    public function test_removing_special_membership_does_not_change_profile(): void
    {
        $user = User::factory()->create([
            'isProfileComplete' => false,
            'has_lifetime_access' => true,
            'membership_type' => 'content_creator',
        ]);

        $request = Request::create('/membership', 'PATCH', [
            'membership_type' => 'none',
        ]);

        app(UserController::class)->updateMembership($request, (string) $user->id);

        $user->refresh();
        $this->assertFalse($user->has_lifetime_access);
        $this->assertNull($user->membership_type);
        $this->assertFalse($user->isProfileComplete);
    }
}
