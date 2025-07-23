<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Support\Facades\Log;

class PhotoUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'photo' => 'required|string', // base64 sin encabezado
            'folder' => 'required|string', // ej: ProfilePhotos, IdentityVerification
        ]);

        $base64Image = $request->input('photo');
        $folder = $request->input('folder');

        $imageData = base64_decode($base64Image);
        if ($imageData === false) {
            return response()->json(['error' => 'Formato Base64 inválido'], 400);
        }

        $tempFilePath = tempnam(sys_get_temp_dir(), 'upload_') . '.jpg';
        if (file_put_contents($tempFilePath, $imageData) === false) {
            return response()->json(['error' => 'No se pudo guardar el archivo'], 500);
        }

        try {
            $result = (new UploadApi())->upload($tempFilePath, [
                'folder' => $folder,
            ]);
            unlink($tempFilePath);

            return response()->json([
                'url' => $result['secure_url'],
            ]);
        } catch (\Exception $e) {
            unlink($tempFilePath);
            Log::error("Error al subir imagen a Cloudinary: {$e->getMessage()}");
            return response()->json([
                'error' => 'Error al subir imagen',
                'trace' => $e->getMessage(),
            ], 500);
        }
    }
}
