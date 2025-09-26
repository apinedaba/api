<?php
namespace App\Services;
use OpenAI\Client;

class AiDiagnosisService {
    protected $client;

    public function __construct()
    {
        $this->client = \OpenAI::client(env('OPENAI_API_KEY'));
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
