<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrevoSmsService
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.brevo.sms_key');
    }

    public function send($to, $message, $type = 'transactional')
    {
        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.brevo.com/v3/transactionalSMS/sms', [
                    'sender' => 'MindMeet',
                    'recipient' => "52" . $to,
                    'content' => $message,
                    'type' => $type,
                ]);

        return $response->json();
    }
}