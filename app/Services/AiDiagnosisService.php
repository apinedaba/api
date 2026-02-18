<?php
namespace App\Services;
use OpenAI\Client;

class AiDiagnosisService
{
    protected $client;

    public function __construct()
    {
        $this->client = \OpenAI::client('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');
    }

    public function generateDiagnosis($symptoms)
    {
        $response = $this->client->completions()->create([
            'model' => 'auto',
            'prompt' => "Basado en el DSM5 de psicología, analiza estos síntomas y sugiere un posible diagnóstico: " . $symptoms,
            'max_tokens' => 200
        ]);

        return $response['choices'][0]['text'] ?? 'No se pudo generar un diagnóstico.';
    }
}
