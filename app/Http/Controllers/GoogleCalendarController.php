<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\GoogleAccount;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GoogleCalendarController extends Controller
{
    public function handleCallback(Request $request, GoogleCalendarService $googleCalendarService)
    {
        $user = Auth::user();
        $appointmentId = session('pending_google_sync_appointment_id');

        if (!$appointmentId) {
            return redirect(env('FRONTEND_URL_USER', 'http://localhost:3000') . '/calendar?error=no_pending_sync');
        }

        try {
            $tokens = $googleCalendarService->fetchTokensWithAuthCode($request->get('code'));
            if (empty($tokens['refresh_token'])) {
                Log::warning('No se recibió refresh_token para el usuario ' . $user->id . ' en el callback. Puede que ya estuviera autorizado.');
            }

            GoogleAccount::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'] ?? $user->googleAccount->refresh_token,
                    'expires_in' => $tokens['expires_in'],
                ]
            );

            $appointment = Appointment::findOrFail($appointmentId);
            $googleCalendarService->createEvent($appointment, $user->fresh());

            session()->forget('pending_google_sync_appointment_id');
            return redirect(env('FRONTEND_URL_USER', 'http://localhost:3000') . '/calendar?success=google_sync_complete');
        } catch (\Exception $e) {
            Log::error('Error en el callback de Google Calendar: ' . $e->getMessage());
            session()->forget('pending_google_sync_appointment_id');
            return redirect(env('FRONTEND_URL_USER', 'http://localhost:3000') . '/calendar?error=google_auth_failed');
        }
    }

    public function checkConnectionStatus(Request $request)
    {
        $isConnected = $request->user()->googleAccount()->whereNotNull('refresh_token')->exists();
        return response()->json(['isConnected' => $isConnected]);
    }
}
