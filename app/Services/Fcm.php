<?php

namespace App\Services;

use App\Models\DeviceToken;
use Google\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Fcm
{
    protected static function getAccessToken()
    {
        return Cache::remember('fcm_access_token', now()->addMinutes(45), function () {
            $client = new Client();
            $client->setAuthConfig(storage_path('app/fcm-service-account.json'));
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            return $client->fetchAccessTokenWithAssertion()['access_token'];
        });
    }

    public static function send(string $deviceToken, string $title, string $body, array $data = [])
    {
        try {
            $accessToken = self::getAccessToken();
            $projectId = config('services.fcm.project_id'); // pon tu ID de proyecto de Firebase en .env

            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            $response = Http::withToken($accessToken)
                ->connectTimeout(2)
                ->timeout(4)
                ->post($url, [
                    "message" => [
                        "token" => $deviceToken,
                        "notification" => [
                            "title" => $title,
                            "body" => $body,
                        ],
                        "data" => [
                            "title" => $title,
                            "body" => $body,
                            "link" => $data['link'] ?? '',
                            "icon" => $data['icon'] ?? '',
                            "type" => $data['type'] ?? '',
                            "id" => $data['id'] ?? '',
                        ],
                        "webpush" => [
                            "fcm_options" => [
                                "link" => $data['link'] ?? '',
                            ],
                        ],
                    ],
                ]);

            Log::info("FCM v1 response", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            $body = $response->json();
            if ($response->status() != 200) {
                if (isset($body['error']['details'][0]['errorCode']) && $body['error']['details'][0]['errorCode'] == "UNREGISTERED") {
                    DeviceToken::where('token', $deviceToken)->delete();
                }
                return false;
            }

            return true;
        } catch (\Throwable $th) {
            Log::warning('FCM send skipped: ' . $th->getMessage(), [
                'token_hash' => hash('sha256', $deviceToken),
            ]);
            return false;
        }
    }
}
