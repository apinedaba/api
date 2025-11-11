<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;


class ProfileController extends Controller
{
        public function index()
    {
        $user = Auth::user();
        $profile = User::where("id", $user->id)->first();
        return response()->json($profile, 200);
    }
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

        public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->except(['email_verified_at', 'created_at', 'updated_at', 'id', 'password']);

        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }
        if ($user instanceof \Illuminate\Database\Eloquent\Model) {
            $user->update($data);
        } else {
            return response()->json(['error' => 'Usuario no válido'], 400);
        }

        // 4. Prepara y devuelve la respuesta.
        $response = [
            'rasson' => 'Tu información se ha actualizado correctamente',
            'message' => "Usuario actualizado",
            'type' => "success"
        ];
        return response()->json($response, 200);
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
            return response()->json(['error' => 'Error al subir la foto', 'trace' => $e->getMessage()], 500);
        }
    }
}
