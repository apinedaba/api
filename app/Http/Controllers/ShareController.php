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

            // 2) Slug limpio
            $safeSlug = $slug ?: Str::slug($pro->name, '-', 'es');

            // 3) URL del FRONTEND (perfil del usuario)  <-- AQUÍ ES A DONDE REGRESAMOS
            $spaBase = rtrim(config('app.frontend_url', 'https://mindmeet.com.mx'), '/');
            $spaUrl = "{$spaBase}/psicologos/{$pro->id}/{$safeSlug}";

            // 4) URL del PROXY (la que ve FB/WA en og:url)
            $shareUrl = $request->fullUrl();

            // 5) Imagen OG (debe ser pública, https y content-type image/*)
            $ogImage = $pro->photo_url ?: "{$spaBase}/assets/og/profile-placeholder.jpg";

            return response()->view('share.professional', [
                'name' => $pro->name,
                'bio' => $pro->bio,
                'spaUrl' => $spaUrl,   // <- redirección para humanos
                'shareUrl' => $shareUrl, // <- og:url
                'ogImage' => $ogImage,
            ])->header('Content-Type', 'text/html; charset=UTF-8');
        } catch (\Throwable $th) {
            header("Location: " . "https://mindmeet.com.mx");
            exit();
        }
    }
}