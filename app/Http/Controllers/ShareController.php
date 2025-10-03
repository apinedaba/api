<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
// use App\Models\Professional; // ajusta a tu modelo real

class ShareController extends Controller
{
    public function professional($id, $slug = null, Request $request)
    {
        // 1) Obtén al profesional desde tu DB
        // $pro = Professional::with('profile')->findOrFail($id);
        // --- DEMO (ajusta a tu shape real):
        try {
            $user = User::findOrFail($id);
            $pro = (object) [
                'id' => (int) $id,
                'name' => $user['contacto']['publicName'] ? $user['contacto']['publicName'] : $user['name'],
                'bio' => $user['educacion']['littleDescription'],
                // Debe ser URL ABSOLUTA pública (https) hacia tu imagen de perfil
                'photo_url' => $user['image'],
            ];

            $safeSlug = Str::slug($pro->name, '-', 'es');

            // URL del SPA (para humanos)
            $spaUrl = "https://mindmeet.com.mx/psicologos/{$pro->id}/{$safeSlug}";

            // URL del PROXY EXACTA que está visitando el bot (para og:url)
            $shareUrl = $request->fullUrl();

            return response()->view('share.professional', [
                'name' => $pro->name,
                'bio' => $pro->bio,
                'spaUrl' => $spaUrl,
                'shareUrl' => $shareUrl,
                'ogImage' => $pro->photo_url ?: "https://mindmeet.com.mx/assets/og/profile-placeholder.jpg",
            ], 200)->header('Content-Type', 'text/html; charset=UTF-8');
        } catch (\Throwable $th) {
            header("Location: " . "https://mindmeet.com.mx");
            exit();
        }
    }
}