<?php

namespace Tests\Feature;

use App\Models\AiSessionCopilotDraft;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\User;
use App\Services\DeepSeekSessionCopilotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SessionCopilotTest extends TestCase
{
    use DatabaseTransactions;

    public function test_professional_can_generate_and_apply_reviewed_closure(): void
    {
        [$professional, $appointment] = $this->context();
        Sanctum::actingAs($professional);

        $service = Mockery::mock(DeepSeekSessionCopilotService::class);
        $service->shouldReceive('close')->once()->andReturn([
            'output' => [
                'objective' => 'Explorar detonantes de ansiedad.',
                'session_description' => 'La persona describió situaciones laborales documentadas.',
                'interventions' => 'Psicoeducación y registro de pensamientos.',
                'action_plan' => 'Completar registro durante la semana.',
                'observations' => 'Borrador sujeto a revisión.',
            ],
            'model' => 'deepseek-test',
            'token_usage' => ['total_tokens' => 120],
        ]);
        $this->app->instance(DeepSeekSessionCopilotService::class, $service);

        $draftId = $this->postJson("/api/user/appointments/{$appointment->id}/copilot/close", [
            'raw_notes' => 'La sesión abordó ansiedad relacionada con situaciones laborales.',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/user/appointments/{$appointment->id}/copilot/{$draftId}/apply", [
            'objective' => 'Objetivo revisado por el profesional.',
            'session_description' => 'Descripción revisada.',
            'interventions' => 'Intervención revisada.',
            'action_plan' => 'Plan revisado.',
            'observations' => 'Observación revisada.',
        ])->assertOk()->assertJsonPath('data.status', 'applied');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'objective' => 'Objetivo revisado por el profesional.',
            'action_plan' => 'Plan revisado.',
        ]);
        $this->assertDatabaseHas('ai_session_copilot_drafts', [
            'id' => $draftId,
            'status' => 'applied',
        ]);
    }

    public function test_other_professional_cannot_read_copilot_history(): void
    {
        [, $appointment] = $this->context();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/user/appointments/{$appointment->id}/copilot")->assertForbidden();
    }

    private function context(): array
    {
        $professional = User::factory()->create();
        $patient = Patient::create([
            'name' => 'Paciente Copiloto',
            'email' => 'copilot+'.uniqid().'@mindmeet.test',
            'password' => Hash::make('secret'),
        ]);
        PatientUser::create(['user' => $professional->id, 'patient' => $patient->id, 'activo' => true]);
        $appointment = Appointment::create([
            'user' => $professional->id,
            'patient' => $patient->id,
            'title' => 'Sesión de copiloto',
            'start' => now()->addDay(),
            'end' => now()->addDay()->addHour(),
        ]);

        return [$professional, $appointment];
    }
}
