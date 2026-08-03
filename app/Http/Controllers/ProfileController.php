<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
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

        return Redirect::route('profile.edit.su');
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

    public function updateServiceSetupProgress(Request $request)
    {
        $validated = $request->validate([
            'current_step' => ['required', 'string', 'in:license,specialties,schedule,services,certifications'],
            'completed_step' => ['nullable', 'string', 'in:license,specialties,schedule,services,certifications'],
        ]);

        $user = $request->user();
        $configurations = $user->configurations ?? [];
        $completedSteps = collect(data_get($configurations, 'service_setup_completed_steps', []))
            ->filter(fn ($step) => in_array($step, ['license', 'specialties', 'schedule', 'services', 'certifications'], true));

        if (filled($validated['completed_step'] ?? null)) {
            $completedSteps->push($validated['completed_step']);
        }

        $configurations['service_setup_current_step'] = $validated['current_step'];
        $configurations['service_setup_completed_steps'] = $completedSteps->unique()->values()->all();
        $configurations['service_setup_updated_at'] = now()->toISOString();

        $user->forceFill(['configurations' => $configurations])->save();

        return response()->json($user->fresh()->load('subscription', 'escuelas'));
    }

    public function documentPreferences(Request $request)
    {
        $preferences = data_get($request->user()->configurations, 'document_preferences', []);

        return response()->json([
            'consent_content' => data_get($preferences, 'consent_content'),
            'professional_signature_data_url' => data_get($preferences, 'professional_signature_data_url'),
            'documents' => array_values(data_get($preferences, 'documents', [])),
            'updated_at' => data_get($preferences, 'updated_at'),
        ]);
    }

    public function updateDocumentPreferences(Request $request)
    {
        $validated = $request->validate([
            'consent_content' => ['required', 'string', 'max:30000'],
            'professional_signature_data_url' => ['nullable', 'string', 'max:2000000'],
            'documents' => ['sometimes', 'array', 'max:100'],
            'documents.*.id' => ['required', 'string', 'max:100'],
            'documents.*.title' => ['required', 'string', 'max:160'],
            'documents.*.content' => ['required', 'string', 'max:30000'],
            'documents.*.requires_signature' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $configurations = $user->configurations ?? [];
        $configurations['document_preferences'] = [
            'consent_content' => trim($validated['consent_content']),
            'professional_signature_data_url' => $validated['professional_signature_data_url'] ?? null,
            'documents' => collect($validated['documents'] ?? data_get($configurations, 'document_preferences.documents', []))
                ->map(fn ($document) => [
                    'id' => $document['id'],
                    'title' => trim($document['title']),
                    'content' => trim($document['content']),
                    'requires_signature' => (bool) $document['requires_signature'],
                ])->values()->all(),
            'updated_at' => now()->toISOString(),
        ];
        $user->forceFill(['configurations' => $configurations])->save();

        return $this->documentPreferences($request);
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
            \Log::error('Error al subir la foto a Cloudinary: ' . $e->getMessage());
            return response()->json(['error' => 'Error al subir la foto', 'trace' => $e->getMessage()], 500);
        }
    }
}
