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

            // 2) Construye el slug (usa helpers de Laravel)
            $safeSlug = Str::slug($pro->name, '-', 'es'); // quita acentos y espacios
            // 3) URL real del SPA (frontend)
            $spaUrl = "https://mindmeet.com.mx/psicologos/{$pro->id}/{$safeSlug}?v=2";
            // URL del PROXY (para bots y og:url)
            $shareUrl = route('share.professional', ['id' => $id, 'slug' => $safeSlug]);
            // 4) Foto de perfil (absoluta). Si no tiene, usa un placeholder de tu dominio
            $ogImage = $pro->photo_url ?: "https://mindmeet.com.mx/assets/og/profile-placeholder.jpg";

            // 5) Renderiza la vista con OG + redirección
            return response()->view('share.professional', [
                'name' => $pro->name,
                'bio' => $pro->bio,
                'spaUrl' => $spaUrl,
                'shareUrl' => $shareUrl,
                'ogImage' => $ogImage,
            ], 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        } catch (\Throwable $th) {
            header("Location: " . "https://mindmeet.com.mx");
            exit();
        }
    }
}