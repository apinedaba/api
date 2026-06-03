<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationWorkspaceFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_independent_psychologist_registration_creates_active_individual_workspace(): void
    {
        Notification::fake();

        $email = 'independiente+' . uniqid() . '@mindmeet.test';

        $this->postJson('/api/user/register', [
            'account_type' => 'independent',
            'name' => 'Dra Registro Independiente',
            'email' => $email,
            'password' => 'secret123',
            'contacto' => [
                'telefono' => '5512345678',
            ],
        ])->assertOk();

        $user = User::where('email', $email)->firstOrFail();

        $this->assertDatabaseHas('organizations', [
            'owner_id' => $user->id,
            'type' => Organization::TYPE_INDIVIDUAL,
        ]);
        $this->assertDatabaseHas('organization_user', [
            'user_id' => $user->id,
            'status' => 'active',
            'role' => 'owner',
        ]);
        $this->assertSame('independent', data_get($user->fresh()->configurations, 'workspace_type'));
        $this->assertNotNull(data_get($user->fresh()->configurations, 'active_organization_id'));

        $token = $this->verifyAndGetToken($email, $user->verification_code);

        $this->withToken($token)
            ->getJson('/api/user/organizations')
            ->assertOk()
            ->assertJsonPath('data.0.type', Organization::TYPE_INDIVIDUAL);

        $this->withToken($token)
            ->getJson('/api/user/info')
            ->assertOk()
            ->assertJsonPath('email', $email);
    }

    public function test_clinic_registration_creates_clinic_workspace_and_can_add_psychologist(): void
    {
        Notification::fake();

        $email = 'clinica+' . uniqid() . '@mindmeet.test';
        $clinicName = 'Clinica MindMeet QA ' . uniqid();

        $this->postJson('/api/user/register', [
            'account_type' => 'clinic',
            'clinic_name' => $clinicName,
            'name' => 'Dra Responsable Clinica',
            'email' => $email,
            'password' => 'secret123',
            'contacto' => [
                'telefono' => '5598765432',
            ],
        ])->assertOk();

        $owner = User::where('email', $email)->firstOrFail();
        $clinic = Clinic::where('owner_user_id', $owner->id)->firstOrFail();

        $this->assertSame($clinicName, $clinic->name);
        $this->assertSame(6, $clinic->base_psychologist_limit);
        $this->assertFalse((bool) data_get($clinic->settings, 'unlimited_psychologists'));
        $this->assertDatabaseHas('organizations', [
            'owner_id' => $owner->id,
            'type' => Organization::TYPE_CLINIC,
        ]);
        $this->assertDatabaseHas('clinic_memberships', [
            'clinic_id' => $clinic->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        $this->assertSame('clinic', data_get($owner->fresh()->configurations, 'workspace_type'));
        $this->assertSame($clinic->id, data_get($owner->fresh()->configurations, 'clinic_id'));

        $token = $this->verifyAndGetToken($email, $owner->verification_code);

        $this->withToken($token)
            ->getJson('/api/user/clinics')
            ->assertOk()
            ->assertJsonFragment(['name' => $clinicName]);

        $psychologistEmail = 'psicologo.clinica+' . uniqid() . '@mindmeet.test';
        $this->withToken($token)
            ->postJson("/api/user/clinics/{$clinic->id}/psychologists", [
                'name' => 'Psicologo Alta Clinica',
                'email' => $psychologistEmail,
                'telefono' => '5511122233',
                'password' => 'secret123',
                'role' => 'psychologist',
                'can_manage_schedule' => true,
                'can_manage_patients' => true,
                'can_view_finance' => false,
                'allowed_modules' => ['dashboard', 'patients', 'agenda'],
            ])
            ->assertCreated();

        $psychologist = User::where('email', $psychologistEmail)->firstOrFail();
        $this->assertSame('clinic_member', data_get($psychologist->configurations, 'workspace_type'));
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $psychologist->id,
            'stripe_status' => 'clinic_managed',
        ]);
        $this->assertTrue(
            ClinicMembership::where('clinic_id', $clinic->id)
                ->where('user_id', $psychologist->id)
                ->where('role', 'psychologist')
                ->exists()
        );
    }

    private function verifyAndGetToken(string $email, string $code): string
    {
        return $this->postJson('/api/user/verify-registration-code', [
            'email' => $email,
            'code' => $code,
        ])
            ->assertOk()
            ->json('token');
    }
}
