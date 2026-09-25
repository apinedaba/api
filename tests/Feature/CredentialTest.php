<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_receives_only_their_private_credential_data(): void
    {
        $patient = Patient::query()->create([
            'name' => 'Paciente de prueba', 'email' => 'patient@example.test', 'password' => bcrypt('password'), 'activo' => true,
        ]);
        Sanctum::actingAs($patient);

        $this->getJson('/api/patient/credential?patient_id=999')
            ->assertOk()
            ->assertJsonPath('userType', 'patient')
            ->assertJsonPath('fullName', 'Paciente de prueba')
            ->assertJsonPath('publicProfileUrl', null)
            ->assertJsonMissing(['qrSvg'])
            ->assertJsonMissing(['email', 'phone', 'address', 'historial']);
    }

    public function test_token_login_does_not_require_csrf_for_a_stateful_origin(): void
    {
        User::factory()->create(['email' => 'psychologist@example.test', 'password' => bcrypt('password')]);

        $this->withHeader('Origin', 'http://localhost:3001')
            ->postJson('/api/user/login', ['email' => 'psychologist@example.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_publicly_visible_psychologist_receives_the_current_public_profile_url(): void
    {
        config(['app.front_url_user' => 'https://mindmeet.com.mx']);

        $user = User::factory()->create([
            'name' => 'Adara Pérez Segovia',
            'contacto' => ['publicName' => 'Psic. Adara Pérez Segovia'],
            'activo' => true,
            'isProfileComplete' => true,
            'identity_verification_status' => 'approved',
            'email_verified_at' => now(),
            'has_lifetime_access' => true,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/user/credential?user_id=999')
            ->assertOk()
            ->assertJsonPath('userType', 'psychologist')
            ->assertJsonPath('verified', true)
            ->assertJsonPath('publicProfileUrl', 'https://mindmeet.com.mx/psicologos/' . $user->id . '/psic-adara-perez-segovia')
            ->assertJsonStructure(['credentialId', 'fullName', 'photoUrl', 'status', 'qrSvg'])
            ->assertJsonMissing(['email', 'telefono', 'address', 'contacto']);
    }

    public function test_psychologist_without_a_public_profile_does_not_receive_a_qr_value(): void
    {
        $user = User::factory()->create(['activo' => false, 'identity_verification_status' => 'pending']);
        Sanctum::actingAs($user);

        $this->getJson('/api/user/credential')
            ->assertOk()
            ->assertJsonPath('publicProfileUrl', null)
            ->assertJsonPath('qrSvg', null);
    }
}
