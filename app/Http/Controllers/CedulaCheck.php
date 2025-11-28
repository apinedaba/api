<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CedulaCheck extends Controller
{
    /**
     * Genera o recupera token de SEP con cache.
     */
    public function getToken()
    {
        // Verifica si ya lo tenemos en cache
        if (Cache::has('sep_token')) {
            return response()->json([
                'token' => Cache::get('sep_token'),
                'cached' => true
            ]);
        }

        $url = env('SEP_BASE_URL') . '/auth/token';

        $response = Http::asForm()->post($url, [
            'clientId' => env('SEP_CLIENT_ID'),
            'apiKey' => env('SEP_API_KEY'),
            'grant_type' => 'client_credentials'
        ]);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Error al obtener token',
                'response' => $response->json()
            ], 500);
        }

        $token = $response->json()['access_token'];

        // Guardar token por 1 hora
        Cache::put('sep_token', $token, now()->addMinutes(55));

        return response()->json([
            'token' => $token,
            'cached' => false
        ]);
    }

    /**
     * Busca una cédula usando el token de SEP.
     */
    public function buscarCedula(Request $request)
    {
        $request->validate([
            'cedula' => 'required'
        ]);

        // Obtener token (auto cacheado)
        $token = Cache::remember('sep_token', 55 * 60, function () {
            $url = env('SEP_BASE_URL') . '/auth/token';
            $response = Http::asForm()->post($url, [
                'clientId' => env('SEP_CLIENT_ID'),
                'apiKey' => env('SEP_API_KEY'),
                'grant_type' => 'client_credentials'
            ]);

            return $response->json()['access_token'];
        });

        $cedula = $request->cedula;

        $url = env('SEP_BASE_URL') . '/cedula/' . $cedula;

        $response = Http::withToken($token)->get($url);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Error al consultar cédula',
                'response' => $response->json()
            ], 500);
        }

        return $response->json();
    }
}
