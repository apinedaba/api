<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Cloudinary\Api\Upload\UploadApi;
class ProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $profile = User::where("id", $user->id)->first();
        return response()->json($profile, 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $profile = User::where("id", $user->id);
        $count = $profile->count();
        $data = $request->all();

        if (isset($data["password"])) {
            $data["password"] = Hash::make($request->password);
        }
        if ($count > 0) {
            $profile->update($data);
            $profile = User::where("id", $user->id)->first();
            $response = [
                'rasson' => 'Tu información se a actualizado correctamente',
                'message' => "Usuario actulizado ",
                'type' => "success"
            ];
            return response()->json($response, 200);
        }
    }
    public function upload(Request $request)
    {
        $request->validate([
            'photo' => 'required|string', // La foto se envía como Base64
        ]);

        // Obtener el Base64 de la solicitud
        $base64Image = $request->input('photo');

        // Decodificar el Base64 a un archivo temporal
        $imageData = base64_decode($base64Image);
        if ($imageData === false) {
            return response()->json(['error' => 'Formato Base64 inválido'], 400);
        }

        // Crear archivo temporal
        $tempFilePath = tempnam(sys_get_temp_dir(), 'photo') . '.jpg'; // Agregar extensión para evitar problemas
        if (file_put_contents($tempFilePath, $imageData) === false) {
            return response()->json(['error' => 'No se pudo guardar el archivo'], 500);
        }

        // Subir el archivo a Cloudinary
        try {
            $result = new UploadApi;
            $result = $result->upload($tempFilePath, [
                'folder' => 'ProfilePhotos',
            ]);

            // Eliminar el archivo temporal después de subirlo
            unlink($tempFilePath);

            return response()->json([
                'url' => $result['secure_url'],
            ]);
        } catch (\Exception $e) {
            unlink($tempFilePath); // Asegurar que se borra el archivo
            Log::error('Error al subir la foto a Cloudinary: ' . $e->getMessage());
            return response()->json(['error' => 'Error al subir la foto'], 500);
        }
    }
    /**
     * Display the specified resource.
     */
    public function show(User $profile)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $profile)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $profile)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $profile)
    {
        //
    }
}
