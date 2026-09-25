<?php
namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\User;
use Database\Seeders\PlanFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanFeatureSeeder::class);
    }

    public function test_new_and_existing_unsubscribed_users_resolve_to_free(): void
    {
        $user = User::factory()->create();
        app(\App\Services\PlanAssignmentService::class)->activateFree($user);
        $this->assertSame('free', app(\App\Services\PlanAccessService::class)->currentPlan($user)->code);
        $this->assertSame('free', $user->subscription()->value('stripe_status'));
        $this->assertNull($user->subscription()->value('stripe_id'));
        $this->assertTrue($user->canUseFeature('digital_record'));
        $this->assertFalse($user->canUseFeature('ai_reports'));
        $this->assertSame(5, $user->featureLimit('patients'));
    }

    public function test_patient_limit_is_counted_in_backend(): void
    {
        $user = User::factory()->create();
        collect(range(1, 5))->each(function ($number) use ($user) {
            $patient = Patient::create(['name' => "Paciente {$number}", 'email' => "patient{$number}@example.test", 'password' => 'test']);
            PatientUser::create(['user' => $user->id, 'patient' => $patient->id, 'activo' => true]);
        });
        $this->assertSame(5, $user->featureUsage('patients'));
        $this->assertFalse($user->canUseMore('patients'));
    }

}
