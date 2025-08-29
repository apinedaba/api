<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User; // Modelo para Profesionales
use App\Models\Patient; // Modelo para Pacientes
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Exception;
use Illuminate\Support\Facades\Log;

class SocialiteController extends Controller
{
    /**
     * Redirige al usuario al proveedor de autenticación.
     * @param string $provider El proveedor (e.g., 'google', 'facebook')
     */
    public function redirectProfessional($provider)
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * Obtiene la información del usuario del proveedor y gestiona el login/registro.
     * @param string $provider El proveedor
     */
    public function callbackProfessional($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            // Busca si ya existe un usuario con este email
            $user = User::where('email', $socialUser->email)->first();

            if ($user) {
                // Si el usuario ya existe, se enlaza la cuenta social actualizando los campos del proveedor.
                // La contraseña original se conserva.
                $user->update([
                    'provider_name' => $provider,
                    'provider_id' => $socialUser->id,
                    'avatar' => $user->avatar ?? $socialUser->avatar, // Actualiza el avatar si no tiene uno
                ]);
            } else {
                // Si no existe, se crea un nuevo usuario.
                $user = User::create([
                    'name' => $socialUser->name,
                    'email' => $socialUser->email,
                    'provider_name' => $provider,
                    'provider_id' => $socialUser->id,
                    'avatar' => $socialUser->avatar,
                    'password' => Hash::make(uniqid()), // Se asigna una contraseña única que no se usará
                    'email_verified_at' => now(), // El email del proveedor se considera verificado
                ]);
            }

            // Se crea el token de acceso para la API y se redirige al frontend.
            $token = $user->createToken('user_token')->plainTextToken;
            return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/auth/callback?token=' . $token);
        } catch (Exception $e) {
            Log::error('Socialite Callback Error (Professional): ' . $e->getMessage());
            return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/login?error=social_auth_failed');
        }
    }

    // --- LÓGICA PARA PACIENTES (análoga a la de profesionales) ---

    public function redirectPatient($provider)
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function callbackPatient($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            $patient = Patient::where('email', $socialUser->email)->first();

            if ($patient) {
                $patient->update([
                    'provider_name' => $provider,
                    'provider_id' => $socialUser->id,
                    'avatar' => $patient->avatar ?? $socialUser->avatar,
                ]);
            } else {
                $patient = Patient::create([
                    'name' => $socialUser->name,
                    'email' => $socialUser->email,
                    'provider_name' => $provider,
                    'provider_id' => $socialUser->id,
                    'avatar' => $socialUser->avatar,
                    'password' => Hash::make(uniqid()),
                ]);
            }

            $token = $patient->createToken('patient_token')->plainTextToken;
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/auth/callback?token=' . $token . '&userType=patient');
        } catch (Exception $e) {
            Log::error('Socialite Callback Error (Patient): ' . $e->getMessage());
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/iniciar-sesion?error=social_auth_failed');
        }
    }
}
