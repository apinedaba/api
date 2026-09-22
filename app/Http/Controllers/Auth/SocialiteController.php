<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User; // Modelo para Profesionales
use App\Models\Patient; // Modelo para Pacientes
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Notifications\NuevoPsicologoRegistrado;
use App\Notifications\NuevoPacienteBienvenida;
use App\Models\Subscription;
use App\Models\SocialRegistrationIntent;
use Illuminate\Support\Str;

class SocialiteController extends Controller
{
    /**
     * Redirige al usuario al proveedor de autenticación.
     * @param string $provider El proveedor (e.g., 'google', 'facebook')
     */
    public function redirectProfessional($provider)
    {
        return Socialite::driver($provider)
            ->stateless()
            ->redirectUrl(config('services.google.redirect_uri_user')) // Le decimos qué URL usar
            ->redirect();
    }

    /**
     * Obtiene la información del usuario del proveedor y gestiona el login/registro.
     * @param string $provider El proveedor
     */
    public function callbackProfessional($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->redirectUrl(config('services.google.redirect_uri_user'))
                ->user();

            $user = User::where('email', $socialUser->email)->first();

            if ($user) {
                // La identidad ya fue confirmada por Google; no corresponde
                // pedir un segundo código OTP de correo al volver al panel.
                // Esto también repara cuentas sociales creadas antes de que
                // guardáramos explícitamente esta marca.
                $user->forceFill([
                    'provider_name' => $provider,
                    'provider_id' => $socialUser->id,
                    'avatar' => $user->avatar ?? $socialUser->avatar,
                    'email_verified_at' => $user->email_verified_at ?: now(),
                    'verification_code' => null,
                    'code_expires_at' => null,
                ])->save();
            } else {
                // Google confirma identidad, pero el teléfono sigue siendo un
                // requisito de registro. Guardamos una intención temporal, no
                // una cuenta incompleta, hasta que la persona lo capture.
                $rawToken = Str::random(64);
                SocialRegistrationIntent::updateOrCreate(
                    ['provider_name' => $provider, 'provider_id' => (string) $socialUser->id],
                    [
                        'token_hash' => hash('sha256', $rawToken),
                        'name' => $socialUser->name ?: 'Profesional MindMeet',
                        'email' => mb_strtolower(trim((string) $socialUser->email)),
                        'avatar' => $socialUser->avatar,
                        'expires_at' => now()->addMinutes(15),
                        'completed_at' => null,
                    ]
                );

                return redirect(config('app.front_url_psicologo') . '/registro-google?intent=' . urlencode($rawToken));
            }

            $token = $user->createToken('user_token')->plainTextToken;
            $needsPhone = ! $user->hasValidPhone();
            return redirect(config('app.front_url_psicologo') . '/auth/callback?token=' . $token . ($needsPhone ? '&phone_required=1' : ''));
        } catch (Exception $e) {
            Log::error('Socialite Callback Error (Professional): ' . $e->getMessage());
            return redirect(config('app.front_url_psicologo') . '/login?error=social_auth_failed');
        }
    }

    // --- LÓGICA PARA PACIENTES (análoga a la de profesionales) ---

    public function redirectPatient($provider)
    {
        return Socialite::driver($provider)
            ->stateless()
            ->redirectUrl(config('services.google.redirect_uri_patient')) // Le decimos qué URL usar
            ->redirect();
    }

    public function callbackPatient($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->redirectUrl(config('services.google.redirect_uri_patient'))
                ->user();

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
                    'registration_source' => 'website',
                    'provider_name' => $provider,
                    'provider_id' => $socialUser->id,
                    'avatar' => $socialUser->avatar,
                    'password' => Hash::make(uniqid()),
                ]);
            }

            $token = $patient->createToken('patient_token')->plainTextToken;
            return redirect(config('app.front_url') . '/auth/callback?token=' . $token . '&userType=patient');
        } catch (Exception $e) {
            Log::error('Socialite Callback Error (Patient): ' . $e->getMessage());
            return redirect(config('app.front_url') . '/iniciar-sesion?error=social_auth_failed');
        }
    }
}
