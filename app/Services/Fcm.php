<?php

namespace App\Services;

use Google\Client;
use Illuminate\Support\Facades\Http;

class Fcm
{
    protected static function getAccessToken()
    {
        $client = new Client();
        $client->setAuthConfig(storage_path('app/fcm-service-account.json'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        return $client->fetchAccessTokenWithAssertion()['access_token'];
    }

    public static function send(string $deviceToken, string $title, string $body, array $data = [])
    {
        $accessToken = self::getAccessToken();
        $projectId = config('services.firebase.project_id'); // pon tu ID de proyecto de Firebase en .env

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $response = Http::withToken($accessToken)
            ->post($url, [
                "message" => [
                    "token" => $deviceToken,
                    "data" => [
                        "title" => $title,
                        "body" => $body,
                        "link" => $data['link'] ?? '',
                        "icon" => $data['icon'] ?? '',
                        "type" => $data['type'] ?? '',
                        "id" => $data['id'] ?? '',
                    ],
                ]
            ]);

        \Log::info("FCM v1 response", [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return $response->json();
    }
}