<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalAnalyticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_started_is_accepted(): void
    {
        $professional = User::factory()->create();

        $this->postJson('/api/patient/professional-analytics/events', [
            'user_id' => $professional->id,
            'event_type' => 'checkout_started',
            'session_id' => 'checkout-test-session',
            'metadata' => ['source_step' => 'professional_detail'],
        ])->assertOk()->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('professional_analytics_events', [
            'user_id' => $professional->id,
            'event_type' => 'checkout_started',
        ]);
    }
}
