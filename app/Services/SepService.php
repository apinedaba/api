<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SepService
{
    public function token()
    {
        return Cache::remember('sep_token', now()->addMinutes(55), function () {
            $url = env('SEP_BASE_URL') . '/auth/token';

            $response = Http::asForm()->post($url, [
                'clientId' => env('SEP_CLIENT_ID'),
                'apiKey' => env('SEP_API_KEY'),
                'grant_type' => 'client_credentials'
            ]);

            return $response->json()['access_token'];
        });
    }

    public function buscarCedula($cedula)
    {
        $token = $this->token();

        $url = env('SEP_BASE_URL') . "/cedula/$cedula";

        return Http::withToken($token)->get($url)->json();
    }
}
