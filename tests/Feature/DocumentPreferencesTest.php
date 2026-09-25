<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentPreferencesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_templates_can_be_saved_without_a_professional_signature(): void
    {
        $professional = User::factory()->create();
        Sanctum::actingAs($professional);

        $payload = [
            'consent_content' => '<p style="text-align: center">Consentimiento editado</p>',
            'minor_authorization_content' => '<p>Autorización editada</p>',
            'professional_signature_data_url' => null,
            'documents' => [[
                'id' => 'test-template',
                'title' => 'Riesgo suicida',
                'content' => '<p>Contenido personalizado</p>',
                'requires_signature' => true,
            ]],
        ];

        $this->putJson('/api/user/document-preferences', $payload)
            ->assertOk()
            ->assertJsonPath('consent_content', $payload['consent_content'])
            ->assertJsonPath('documents.0.title', 'Riesgo suicida');

        $this->getJson('/api/user/document-preferences')
            ->assertOk()
            ->assertJsonPath('consent_content', $payload['consent_content'])
            ->assertJsonPath('documents.0.content', '<p>Contenido personalizado</p>');

        $payload['consent_content'] = '<p>Formato MindMeet restablecido</p>';
        $this->putJson('/api/user/document-preferences', $payload)
            ->assertOk()
            ->assertJsonPath('consent_content', $payload['consent_content']);
    }

    public function test_signed_document_captures_the_saved_professional_signature(): void
    {
        Notification::fake();
        $professional = User::factory()->create();
        $signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/XU8AAAAASUVORK5CYII=';
        $professional->forceFill(['configurations' => [
            'document_preferences' => ['professional_signature_data_url' => $signature],
        ]])->save();
        $patient = Patient::create([
            'name' => 'Paciente de prueba',
            'email' => 'documents+'.uniqid().'@mindmeet.test',
            'password' => Hash::make('secret'),
        ]);
        PatientUser::create(['user' => $professional->id, 'patient' => $patient->id, 'activo' => true]);
        Sanctum::actingAs($professional);

        $response = $this->postJson("/api/user/patients/{$patient->id}/document-requests", [
            'template_id' => 'test-template',
            'title' => 'Consentimiento de prueba',
            'content' => '<p>Contenido</p>',
            'requires_signature' => true,
            'signer_name' => $patient->name,
            'signer_role' => 'Paciente',
        ])->assertCreated();

        $this->assertDatabaseHas('patient_document_requests', [
            'user_id' => $professional->id,
            'patient_id' => $patient->id,
            'professional_signature_data_url' => $signature,
        ]);

        $token = basename($response->json('public_url'));
        $this->postJson("/api/public/documents/{$token}/sign", [
            'signer_name' => $patient->name,
            'signature_data_url' => $signature,
        ])->assertOk();
        $this->get("/api/public/documents/{$token}/pdf")->assertOk();
    }
}
