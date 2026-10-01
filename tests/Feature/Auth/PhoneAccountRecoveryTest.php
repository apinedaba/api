<?php

namespace Tests\Feature\Auth;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhoneAccountRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_can_recover_with_registered_phone_and_change_email(): void
    {
        Http::fake();
        $user = User::factory()->create([
            'email' => 'old@example.test',
            'recovery_phone' => '5512345678',
        ]);
        $user->createToken('existing-session');

        $this->postJson('/api/user/account-recovery/request-code', ['phone' => '55 1234 5678'])
            ->assertOk()
            ->assertJsonPath('message', 'Si los datos coinciden, recibirás un código por SMS.');

        $recovery = DB::table('phone_account_recoveries')->first();
        DB::table('phone_account_recoveries')->where('id', $recovery->id)->update(['code_hash' => Hash::make('123456')]);

        $token = $this->postJson('/api/user/account-recovery/verify-code', [
            'phone' => '5512345678',
            'code' => '123456',
        ])->assertOk()->json('recovery_token');

        $this->postJson('/api/user/account-recovery/complete', [
            'recovery_token' => $token,
            'email' => 'new@example.test',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertOk();

        $user->refresh();
        $this->assertSame('new@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_unknown_phone_receives_generic_response_without_creating_recovery(): void
    {
        Http::fake();

        $this->postJson('/api/patient/account-recovery/request-code', ['phone' => '5512345678'])
            ->assertOk()
            ->assertJsonPath('message', 'Si los datos coinciden, recibirás un código por SMS.');

        $this->assertDatabaseCount('phone_account_recoveries', 0);
    }

    public function test_professional_can_sign_in_with_their_unique_phone(): void
    {
        $user = User::factory()->create([
            'recovery_phone' => '5512345678',
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/user/login', [
            'identifier' => '55 1234 5678',
            'password' => 'correct-password',
        ])->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_patient_can_recover_with_their_registered_phone(): void
    {
        Http::fake();
        $patient = Patient::create([
            'name' => 'Paciente de prueba',
            'email' => 'old-patient@example.test',
            'phone' => '5512345678',
            'password' => Hash::make('password'),
        ]);

        $this->postJson('/api/patient/account-recovery/request-code', ['phone' => '5512345678'])->assertOk();
        $recovery = DB::table('phone_account_recoveries')->first();
        DB::table('phone_account_recoveries')->where('id', $recovery->id)->update(['code_hash' => Hash::make('123456')]);
        $token = $this->postJson('/api/patient/account-recovery/verify-code', ['phone' => '5512345678', 'code' => '123456'])
            ->assertOk()->json('recovery_token');

        $this->postJson('/api/patient/account-recovery/complete', [
            'recovery_token' => $token,
            'email' => 'new-patient@example.test',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertOk();

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'email' => 'new-patient@example.test']);
    }
}
