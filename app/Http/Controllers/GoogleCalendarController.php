<?php

namespace App\Http\Controllers;

use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Models\Appointment;
use App\Models\GoogleAccount;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class GoogleCalendarController extends Controller
{
    public function handleCallback(Request $request, GoogleCalendarService $googleCalendarService)
    {
        try {
            $encryptedState = $request->get('state');

            if (!$encryptedState) {
                throw new \Exception('No se recibio el parametro de estado de Google.');
            }

            $statePayload = json_decode(Crypt::decrypt($encryptedState), true);
            $userId = $statePayload['user_id'];
            $appointmentIds = collect($statePayload['appointment_ids'] ?? [])
                ->when(isset($statePayload['appointment_id']), fn ($collection) => $collection->push($statePayload['appointment_id']))
                ->filter()
                ->unique()
                ->values();

            $user = User::findOrFail($userId);
            $appointments = Appointment::whereIn('id', $appointmentIds)->get();

            if ($appointments->isEmpty()) {
                throw new \Exception('No se encontraron citas pendientes por sincronizar.');
            }
        } catch (DecryptException $e) {
            Log::error('Error al desencriptar el estado de Google Calendar.', ['exception' => $e]);
            return redirect(config('app.front_url_psicologo') . '/agenda?error=invalid_state');
        } catch (\Exception $e) {
            Log::error('Error en la validacion inicial del callback de Google: ' . $e->getMessage(), ['exception' => $e]);
            return redirect(config('app.front_url_psicologo') . '/agenda?error=google_auth_failed');
        }

        try {
            $tokens = $googleCalendarService->fetchTokensWithAuthCode($request->get('code'));

            GoogleAccount::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'] ?? $user->googleAccount?->refresh_token,
                    'expires_in' => $tokens['expires_in'],
                ]
            );

            foreach ($appointments as $appointment) {
                SyncAppointmentToGoogleCalendar::dispatch($appointment, $user, 'create');
            }

            return redirect(config('app.front_url_psicologo') . '/agenda?success=google_sync_complete');
        } catch (\Exception $e) {
            Log::error('Error al obtener tokens u organizar el job en el callback: ' . $e->getMessage(), ['exception' => $e]);
            return redirect(config('app.front_url_psicologo') . '/agenda?error=token_fetch_failed');
        }
    }

    public function checkConnectionStatus(Request $request)
    {
        $isConnected = $request->user()->googleAccount()->whereNotNull('refresh_token')->exists();
        return response()->json(['isConnected' => $isConnected]);
    }
}
