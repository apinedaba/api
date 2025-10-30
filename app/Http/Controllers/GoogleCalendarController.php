<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\GoogleAccount;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Jobs\SyncAppointmentToGoogleCalendar;
use Illuminate\Support\Facades\Crypt;
use App\Models\User;

class GoogleCalendarController extends Controller
{
    /**
     * Maneja la redirección de vuelta desde Google OAuth.
     * Este método es 'stateless' y no depende de la sesión.
     *
     * @param Request $request
     * @param GoogleCalendarService $googleCalendarService
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleCallback(Request $request, GoogleCalendarService $googleCalendarService)
    {
        // Primero, validamos y desencriptamos el estado para saber a qué usuario y cita pertenece este callback.
        try {
            $encryptedState = $request->get('state');

            if (!$encryptedState) {
                throw new \Exception('No se recibió el parámetro de estado de Google.');
            }

            // Desencriptamos y decodificamos el payload (los datos que guardamos).
            $statePayload = json_decode(Crypt::decrypt($encryptedState), true);

            // Extraemos los IDs que necesitamos.
            $userId = $statePayload['user_id'];
            $appointmentId = $statePayload['appointment_id'];

            // Buscamos los registros en la base de datos. Si no existen, fallará.
            $user = User::findOrFail($userId);
            $appointment = Appointment::findOrFail($appointmentId);
        } catch (DecryptException $e) {
            Log::error('Error al desencriptar el estado de Google Calendar: Payload inválido o manipulado.', ['exception' => $e]);
            return redirect(env('FRONTEND_URL_USER') . '/agenda?error=invalid_state');
        } catch (\Exception $e) {
            Log::error('Error en la validación inicial del callback de Google: ' . $e->getMessage(), ['exception' => $e]);
            return redirect(env('FRONTEND_URL_USER') . '/agenda?error=google_auth_failed');
        }


        // Si llegamos aquí, ya sabemos quién es el usuario y qué cita sincronizar.
        // Ahora, procedemos a obtener los tokens.
        try {
            $tokens = $googleCalendarService->fetchTokensWithAuthCode($request->get('code'));

            // Guardamos o actualizamos los tokens en la base de datos para el usuario correcto.
            GoogleAccount::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'] ?? $user->googleAccount->refresh_token, // Guardamos el refresh token solo si es nuevo
                    'expires_in' => $tokens['expires_in'],
                ]
            );

            // Despachamos el job para que la sincronización se haga en segundo plano.
            SyncAppointmentToGoogleCalendar::dispatch($appointment, $user, 'create');

            // Redirigimos al usuario al frontend con un mensaje de éxito.
            return redirect(env('FRONTEND_URL_USER') . '/agenda?success=google_sync_complete');
        } catch (\Exception $e) {
            Log::error('Error al obtener tokens u organizar el job en el callback: ' . $e->getMessage(), ['exception' => $e]);
            return redirect(env('FRONTEND_URL_USER') . '/agenda?error=token_fetch_failed');
        }
    }

    public function checkConnectionStatus(Request $request)
    {
        $isConnected = $request->user()->googleAccount()->whereNotNull('refresh_token')->exists();
        return response()->json(['isConnected' => $isConnected]);
    }
}
