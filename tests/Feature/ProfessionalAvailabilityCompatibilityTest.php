<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalAvailabilityCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_public_availability_accepts_legacy_spanish_days_and_time_keys(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 08:00:00', 'America/Mexico_City'));

        $professional = User::factory()->create([
            'timezone' => 'America/Mexico_City',
            'horarios' => [
                'martes' => [
                    ['start_time' => '09:00', 'end_time' => '11:00'],
                ],
            ],
        ]);

        $this->postJson("/api/patient/profesional/{$professional->id}/disponibilidad", [
            'start' => '2026-08-31',
            'end' => '2026-09-02',
            'timezone' => 'America/Mexico_City',
        ])->assertOk()->assertJsonFragment([
            'date' => '2026-09-01',
            'hour' => '09:00',
        ]);
    }

    public function test_invalid_legacy_blocks_do_not_break_valid_availability(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 08:00:00', 'America/Mexico_City'));

        $professional = User::factory()->create([
            'timezone' => 'America/Mexico_City',
            'horarios' => [
                'tuesday' => [
                    ['unexpected' => 'value'],
                    ['start' => '10:00', 'end' => '12:00'],
                ],
            ],
        ]);

        $this->postJson("/api/patient/profesional/{$professional->id}/disponibilidad", [
            'start' => '2026-08-31',
            'end' => '2026-09-02',
            'timezone' => 'America/Mexico_City',
        ])->assertOk()->assertJsonFragment(['hour' => '10:00']);
    }
}
