<?php

namespace App\Services;

use Google\Client;
use Illuminate\Support\Facades\Http;

class Fcm {
    protected static function getAccessToken() {
        $client = new Client();
        $client->setAuthConfig(storage_path('app/fcm-service-account.json'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        return $client->fetchAccessTokenWithAssertion()['access_token'];
    }

    public static function send(string $deviceToken, string $title, string $body, array $data = []) {
        $accessToken = self::getAccessToken();
        $projectId = env('FIREBASE_PROJECT_ID'); // pon tu ID de proyecto de Firebase en .env

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $response = Http::withToken($accessToken)
            ->post($url, [
                "message" => [
                    "token" => $deviceToken,
                    "notification" => [
                        "title" => $title,
                        "body"  => $body,
                    ],
                    "data" => $data,
                ]
            ]);

        \Log::info("FCM v1 response", [
            'status' => $response->status(),
            'body'   => $response->body()
        ]);

        return $response->json();
    }
}