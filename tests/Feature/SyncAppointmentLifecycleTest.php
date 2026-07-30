<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentCart;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\SessionStartCodeService;
use App\Services\PaymentSettlementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncAppointmentLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_panel_appointments_do_not_require_a_start_code(): void
    {
        [$appointment] = $this->appointment(now()->subMinutes(10), now()->addMinutes(40));

        $this->assertNull($appointment->session_start_code_hash);
        $this->artisan('appointments:sync-lifecycle')->assertSuccessful();
        $this->assertSame('scheduled', $appointment->fresh()->lifecycle_status);
    }

    public function test_professional_code_validation_starts_website_session_and_releases_payment(): void
    {
        [$appointment, $professional] = $this->appointment(
            now()->subMinutes(10),
            now()->addMinutes(40),
            [],
            true
        );
        $payment = $this->payment($appointment);
        $code = app(SessionStartCodeService::class)->reveal($appointment);
        $payment->setRelation('appointment', $appointment);
        $this->assertFalse(app(PaymentSettlementService::class)->isWithdrawable($payment));
        Sanctum::actingAs($professional);

        $this->postJson("/api/user/appointments/{$appointment->id}/start", [
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('appointment.lifecycle_status', 'in_process');

        $appointment->refresh();
        $payment->refresh();
        $this->assertSame('En curso', $appointment->state);
        $this->assertNotNull($appointment->session_start_code_verified_at);
        $this->assertSame('available', $payment->payout_status);
        $this->assertSame(315.0, $payment->psychologist_amount);
    }

    public function test_cron_completes_only_a_session_previously_started_with_code(): void
    {
        [$appointment] = $this->appointment(
            now()->subHour(),
            now()->subMinute(),
            [
                'lifecycle_status' => 'in_process',
                'state' => 'En curso',
                'started_at' => now()->subHour(),
            ],
            true
        );

        $this->artisan('appointments:sync-lifecycle')->assertSuccessful();

        $appointment->refresh();
        $this->assertSame('complete', $appointment->lifecycle_status);
        $this->assertSame('Completada', $appointment->state);
        $this->assertNotNull($appointment->completed_at);
    }

    public function test_patient_can_view_code_only_for_own_website_session(): void
    {
        [$appointment, , $patient] = $this->appointment(
            now()->addHour(),
            now()->addHours(2),
            [],
            true
        );
        Sanctum::actingAs($patient);

        $this->getJson("/api/patient/appointments/{$appointment->id}/start-code")
            ->assertOk()
            ->assertJsonPath('requires_start_code', true)
            ->assertJsonStructure(['code']);

        $this->getJson('/api/patient/appointments/patient')
            ->assertOk()
            ->assertJsonPath('0.requires_start_code', true)
            ->assertJsonStructure([['session_start_code']]);
    }

    public function test_backfill_command_adds_codes_only_to_website_sessions(): void
    {
        [$websiteAppointment] = $this->appointment(now()->addDay(), now()->addDay()->addHour(), [], true);
        [$manualAppointment] = $this->appointment(now()->addDays(2), now()->addDays(2)->addHour());

        $websiteAppointment->forceFill([
            'session_start_code_hash' => null,
            'session_start_code_encrypted' => null,
        ])->saveQuietly();

        $this->artisan('appointments:backfill-start-codes')
            ->assertSuccessful();

        $this->assertNotNull($websiteAppointment->fresh()->session_start_code_hash);
        $this->assertNotNull($websiteAppointment->fresh()->session_start_code_encrypted);
        $this->assertNull($manualAppointment->fresh()->session_start_code_hash);
        $this->assertNull($manualAppointment->fresh()->session_start_code_encrypted);
    }

    public function test_professional_can_validate_code_ten_minutes_before_start(): void
    {
        [$appointment, $professional] = $this->appointment(
            now()->addMinutes(9),
            now()->addMinutes(69),
            [],
            true
        );
        $code = app(SessionStartCodeService::class)->reveal($appointment);
        Sanctum::actingAs($professional);

        $this->postJson("/api/user/appointments/{$appointment->id}/start", ['code' => $code])
            ->assertOk()
            ->assertJsonPath('appointment.lifecycle_status', 'in_process');
    }

    public function test_professional_cannot_validate_code_before_early_window(): void
    {
        [$appointment, $professional] = $this->appointment(
            now()->addMinutes(11),
            now()->addMinutes(71),
            [],
            true
        );
        $code = app(SessionStartCodeService::class)->reveal($appointment);
        Sanctum::actingAs($professional);

        $this->postJson("/api/user/appointments/{$appointment->id}/start", ['code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('rasson', 'El código podrá validarse desde 10 minutos antes de la hora de inicio.');
    }

    private function appointment($start, $end, array $attributes = [], bool $website = false): array
    {
        $professional = User::factory()->create();
        $patient = Patient::create([
            'name' => 'Paciente Lifecycle',
            'email' => 'lifecycle+'.uniqid().'@mindmeet.test',
            'phone' => '5512345678',
            'password' => Hash::make('secret'),
        ]);
        $cart = $website ? AppointmentCart::create([
            'patient_id' => $patient->id,
            'user_id' => $professional->id,
            'fecha' => $start->toDateString(),
            'hora' => $start->format('H:i:s'),
            'tipoSesion' => 'individual_therapy',
            'duracion' => '1',
            'precio' => 350,
            'estado' => 'pagado',
            'source' => 'website',
            'stripe_payment_status' => 'paid',
        ]) : null;

        $appointment = Appointment::create(array_merge([
            'user' => $professional->id,
            'patient' => $patient->id,
            'cart_id' => $cart?->id,
            'title' => 'Sesión lifecycle',
            'start' => $start,
            'end' => $end,
            'statusUser' => 'Confirmed',
            'statusPatient' => 'Confirmed',
            'state' => 'Confirmada',
        ], $attributes));

        return [$appointment, $professional, $patient];
    }

    private function payment(Appointment $appointment): Payment
    {
        return Payment::create([
            'user_id' => $appointment->user,
            'payer_type' => 'patient',
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient,
            'amount' => 371,
            'currency' => 'MXN',
            'payment_method' => 'card',
            'status' => 'completed',
            'stripe_payment_id' => 'pi_lifecycle_'.uniqid(),
            'charge_subtotal_amount' => 350,
            'total_charge_amount' => 371,
            'platform_fee_amount' => 21,
            'stripe_fee_amount' => 21,
        ]);
    }
}
