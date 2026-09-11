<?php

namespace App\Http\Controllers;

use App\Events\NewNotification;
use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Models\Appointment;
use App\Models\GoogleAccount;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Services\MinderSupportGoogleCalendarService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GoogleCalendarController extends Controller
{
    public function handleCallback(Request $request, GoogleCalendarService $googleCalendarService, MinderSupportGoogleCalendarService $minderSupportCalendar)
    {
        try {
            $encryptedState = $request->get('state');

            if (!$encryptedState) {
                throw new \Exception('No se recibio el parametro de estado de Google.');
            }

            $statePayload = json_decode(Crypt::decrypt($encryptedState), true);
            if (($statePayload['mode'] ?? null) === 'minder_support') {
                try {
                    $minderSupportCalendar->connect($request->string('code')->toString());

                    return redirect()->route('minder.support-appointments.index')
                        ->with('success', 'Google Calendar conectado. Las sesiones de apoyo disponibles se agendarán automáticamente con Google Meet.');
                } catch (\Throwable $exception) {
                    Log::error('No fue posible conectar el Google Calendar de soporte.', ['exception' => $exception]);

                    return redirect()->route('minder.support-appointments.index')
                        ->with('error', 'No fue posible conectar Google Calendar. Intenta de nuevo con la cuenta de MindMeet.');
                }
            }
            $userId = $statePayload['user_id'];
            $appointmentIds = collect($statePayload['appointment_ids'] ?? [])
                ->when(isset($statePayload['appointment_id']), fn ($collection) => $collection->push($statePayload['appointment_id']))
                ->filter()
                ->unique()
                ->values();

            $user = User::findOrFail($userId);
            $appointments = Appointment::whereIn('id', $appointmentIds)->get();
            $mode = $statePayload['mode'] ?? 'appointments';

            if ($mode !== 'settings' && $appointments->isEmpty()) {
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
            $user->unsetRelation('googleAccount')->load('googleAccount');
            try {
                $calendars = $googleCalendarService->listCalendars($user);
                $defaultCalendarId = $user->googleAccount?->default_calendar_id;
                $defaultCalendar = collect($calendars)->firstWhere('id', $defaultCalendarId)
                    ?: collect($calendars)->firstWhere('primary', true);
                $googleTimezone = data_get($defaultCalendar, 'timezone');
                if (is_string($googleTimezone) && in_array($googleTimezone, timezone_identifiers_list(), true)) {
                    $user->update(['timezone' => $googleTimezone]);
                }
            } catch (\Throwable $exception) {
                Log::warning('Google se conectó, pero no fue posible adoptar la zona del calendario.', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);
            }

            if ($mode === 'settings') {
                return redirect(config('app.front_url_psicologo') . '/configuracion?section=calendario&google=connected');
            }

            $notifyEachAppointment = $appointments->count() <= 1;

            foreach ($appointments as $appointment) {
                SyncAppointmentToGoogleCalendar::dispatch($appointment, $user, 'create', $notifyEachAppointment);
            }

            if (!$notifyEachAppointment) {
                event(new NewNotification(
                    "user.{$user->id}",
                    "Se estan sincronizando {$appointments->count()} sesiones recurrentes con Google Meet. Te notificamos solo una vez para evitar duplicados."
                ));
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

    public function authUrl(Request $request, GoogleCalendarService $service)
    {
        $state = Crypt::encrypt(json_encode([
            'user_id' => $request->user()->id,
            'appointment_ids' => [],
            'mode' => 'settings',
        ]));

        return response()->json(['url' => $service->getAuthUrl($state)]);
    }

    public function settings(Request $request, GoogleCalendarService $service)
    {
        $user = $request->user()->load('googleAccount');
        $connected = (bool) $user->googleAccount?->refresh_token;
        $calendars = [];
        $needsReconnect = false;

        if ($connected) {
            try {
                $calendars = $service->listCalendars($user);
            } catch (\Throwable $exception) {
                $needsReconnect = true;
                Log::warning('No fue posible listar calendarios de Google.', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json([
            'connected' => $connected,
            'needs_reconnect' => $needsReconnect,
            'timezone' => $service->professionalTimezone($user),
            'default_calendar_id' => $user->googleAccount?->default_calendar_id,
            'rules' => $user->googleAccount?->calendar_sync_rules ?? [],
            'calendars' => $calendars,
        ]);
    }

    public function updateSettings(Request $request, GoogleCalendarService $service)
    {
        $timezoneList = timezone_identifiers_list();
        $data = $request->validate([
            'timezone' => ['nullable', 'string', Rule::in($timezoneList)],
            'default_calendar_id' => ['nullable', 'string', 'max:255'],
            'rules' => ['array', 'max:20'],
            'rules.*.id' => ['nullable', 'string', 'max:64'],
            'rules.*.start_time' => ['required', 'date_format:H:i'],
            'rules.*.end_time' => ['required', 'date_format:H:i', 'different:rules.*.start_time'],
            'rules.*.calendar_id' => ['required', 'string', 'max:255'],
            'rules.*.calendar_name' => ['nullable', 'string', 'max:255'],
            'rules.*.enabled' => ['nullable', 'boolean'],
        ]);

        $user = $request->user()->load('googleAccount');

        if ($user->googleAccount) {
            $calendars = collect($service->listCalendars($user));
            $defaultCalendar = filled($data['default_calendar_id'] ?? null)
                ? $calendars->firstWhere('id', $data['default_calendar_id'])
                : $calendars->firstWhere('primary', true);

            if (! $defaultCalendar) {
                return response()->json(['message' => 'El calendario predeterminado ya no está disponible en Google.'], 422);
            }

            $googleTimezone = data_get($defaultCalendar, 'timezone');
            if (is_string($googleTimezone) && in_array($googleTimezone, $timezoneList, true)) {
                $user->update(['timezone' => $googleTimezone]);
            }

            $rules = collect($data['rules'] ?? [])->map(fn (array $rule) => [
                'id' => $rule['id'] ?? (string) Str::uuid(),
                'start_time' => $rule['start_time'],
                'end_time' => $rule['end_time'],
                'calendar_id' => $rule['calendar_id'],
                'calendar_name' => $rule['calendar_name'] ?? null,
                'enabled' => $rule['enabled'] ?? true,
            ])->values()->all();

            $user->googleAccount->update([
                'default_calendar_id' => $data['default_calendar_id'] ?? null,
                'calendar_sync_rules' => $rules,
            ]);
        }

        return response()->json([
            'message' => 'Configuración de calendario guardada.',
            'timezone' => $user->fresh()->timezone,
        ]);
    }
}
