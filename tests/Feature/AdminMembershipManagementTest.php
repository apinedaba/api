<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\MembershipAdministrativeAction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminMembershipManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_manually_revoke_a_lifetime_membership_once(): void
    {
        Mail::fake();
        $administrator = Administrator::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin+'.uniqid().'@mindmeet.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $professional = User::factory()->create([
            'has_lifetime_access' => true,
            'membership_type' => 'lifetime',
        ]);

        $this->actingAs($administrator)
            ->post(route('psicologo.membership.end', $professional->id), [
                'action' => 'revoke_lifetime',
                'refund' => false,
                'reason' => 'Cuenta inactiva',
                'confirmation' => 'CANCELAR',
            ])
            ->assertRedirect(route('psicologoShow', $professional->id));

        $professional->refresh();
        $this->assertFalse($professional->has_lifetime_access);
        $this->assertNull($professional->membership_type);
        $this->assertDatabaseHas('membership_administrative_actions', [
            'user_id' => $professional->id,
            'administrator_id' => $administrator->id,
            'action' => 'revoke_lifetime',
            'previous_status' => 'lifetime',
        ]);
        $this->assertNotNull(MembershipAdministrativeAction::first()->notification_sent_at);

        $this->actingAs($administrator)
            ->post(route('psicologo.membership.end', $professional->id), [
                'action' => 'revoke_lifetime',
                'confirmation' => 'CANCELAR',
            ])
            ->assertSessionHasErrors('action');

        $this->assertSame(1, MembershipAdministrativeAction::count());
    }
}
