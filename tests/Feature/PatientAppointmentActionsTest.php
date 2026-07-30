<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientAppointmentActionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_professional_created_appointment_starts_accepted_by_professional_only(): void
    {
        $professional = User::factory()->create();
        $patient = $this->makePatient('professional-created+'.uniqid().'@mindmeet.test');
        Sanctum::actingAs($professional);

        $response = $this->postJson('/api/user/appointments', [
            'patient' => $patient->id,
            'title' => 'Sesion creada por profesional',
            'start' => now()->addDay()->toIso8601String(),
            'end' => now()->addDay()->addHour()->toIso8601String(),
        ])->assertOk();

        $appointmentId = $response->json('appointments.0.id');
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'statusUser' => 'Confirmed',
            'statusPatient' => 'Pending Approve',
            'state' => 'Pendiente de confirmacion del paciente',
        ]);
    }

    public function test_patient_can_confirm_own_appointment(): void
    {
        [$patient, $appointment] = $this->makePatientAppointment();
        Sanctum::actingAs($patient);

        $this->patchJson("/api/patient/appointments/{$appointment->id}/status", [
            'status' => 'Confirmed',
        ])
            ->assertOk()
            ->assertJsonPath('appointment.statusPatient', 'Confirmed')
            ->assertJsonPath('appointment.state', 'Confirmada');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'statusPatient' => 'Confirmed',
            'state' => 'Confirmada',
        ]);
    }

    public function test_patient_cannot_manage_another_patient_appointment(): void
    {
        [, $appointment] = $this->makePatientAppointment();
        $otherPatient = $this->makePatient('other+'.uniqid().'@mindmeet.test');
        Sanctum::actingAs($otherPatient);

        $this->patchJson("/api/patient/appointments/{$appointment->id}/status", [
            'status' => 'Cancel',
        ])->assertForbidden();
    }

    public function test_patient_can_upload_payment_proof_for_own_appointment(): void
    {
        Storage::fake('public');
        [$patient, $appointment] = $this->makePatientAppointment();
        Sanctum::actingAs($patient);

        $this->postJson("/api/patient/appointments/{$appointment->id}/payment-proof", [
            'proof' => UploadedFile::fake()->image('comprobante.jpg'),
            'amount' => 750,
            'comments' => 'Transferencia SPEI',
        ])->assertCreated()
            ->assertJsonPath('payment.status', 'pending_review')
            ->assertJsonPath('appointment.payment_status', 'pending_review');

        $payment = Payment::where('appointment_id', $appointment->id)->firstOrFail();
        $this->assertSame('manual_transfer', $payment->payment_method);
        $this->assertSame('pending_review', $payment->status);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', parse_url($payment->receipt_url, PHP_URL_PATH)));
    }

    private function makePatientAppointment(): array
    {
        $professional = User::factory()->create();
        $patient = $this->makePatient('patient+'.uniqid().'@mindmeet.test');
        $appointment = Appointment::create([
            'user' => $professional->id,
            'patient' => $patient->id,
            'title' => 'Sesion de prueba',
            'start' => now()->addDay(),
            'end' => now()->addDay()->addHour(),
        ]);

        return [$patient, $appointment];
    }

    private function makePatient(string $email): Patient
    {
        return Patient::create([
            'name' => 'Paciente Test',
            'email' => $email,
            'phone' => '5512345678',
            'password' => Hash::make('5512345678'),
        ]);
    }
}
