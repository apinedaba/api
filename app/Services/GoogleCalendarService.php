<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleCalendarEvent;
use Illuminate\Support\Facades\Log;

/**
 * Excepción personalizada para manejar tokens de Google revocados o inválidos.
 */
class InvalidGoogleTokenException extends \Exception {}

class GoogleCalendarService
{
    /**
     * El cliente de la API de Google.
     * @var GoogleClient
     */
    protected $client;

    /**
     * Constructor del servicio.
     * Inicializa el cliente de Google con las credenciales de la aplicación.
     */
    public function __construct()
    {
        $this->client = new GoogleClient();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.calendar_redirect_uri'));
        $this->client->setScopes([GoogleCalendar::CALENDAR_EVENTS]);

        // 'offline' nos permite obtener un refresh_token para usar la API sin que el usuario esté conectado.
        $this->client->setAccessType('offline');

        // 'consent' fuerza a que la pantalla de consentimiento aparezca siempre, útil para asegurar la obtención del refresh_token en desarrollo.
        // Puedes comentarlo en producción si lo deseas.
        $this->client->setPrompt('consent');
    }

    /**
     * Obtiene la URL de autenticación de Google, opcionalmente con un parámetro 'state'.
     *
     * @param string|null $state El estado encriptado a pasar.
     * @return string
     */
    public function getAuthUrl(string $state = null): string
    {
        if ($state) {
            $this->client->setState($state);
        }
        return $this->client->createAuthUrl();
    }


    /**
     * Obtiene los tokens de acceso y de refresco usando el código de autorización de Google.
     *
     * @param string $authCode
     * @return array
     */
    public function fetchTokensWithAuthCode(string $authCode): array
    {
        return $this->client->fetchAccessTokenWithAuthCode($authCode);
    }

    /**
     * Prepara y devuelve un cliente autenticado, refrescando el token de acceso si ha expirado.
     *
     * @param User $user El usuario para el que se autenticará el cliente.
     * @return GoogleClient
     * @throws \Exception si el usuario no tiene una cuenta de Google conectada.
     * @throws InvalidGoogleTokenException si el refresh_token es inválido.
     */
    public function getAuthenticatedClient(User $user): GoogleClient
    {
        if (!$user->googleAccount) {
            throw new \Exception("El usuario no tiene una cuenta de Google conectada.");
        }

        // Laravel desencriptará los tokens automáticamente gracias a tu modelo GoogleAccount.
        $tokens = [
            'access_token' => $user->googleAccount->access_token,
            'refresh_token' => $user->googleAccount->refresh_token,
            'expires_in' => $user->googleAccount->expires_in,
            'created' => $user->googleAccount->created_at->getTimestamp(),
        ];

        $this->client->setAccessToken($tokens);

        // Si el token de acceso ha expirado, usamos el de refresco para obtener uno nuevo.
        if ($this->client->isAccessTokenExpired()) {
            try {
                $newTokens = $this->client->fetchAccessTokenWithRefreshToken($user->googleAccount->refresh_token);

                // Actualizamos la cuenta del usuario con el nuevo token de acceso.
                $user->googleAccount->update([
                    'access_token' => $newTokens['access_token'],
                    'expires_in' => $newTokens['expires_in'],
                ]);

                $this->client->setAccessToken($newTokens);
            } catch (\Exception $e) {
                // Si Google responde con 'invalid_grant', significa que el usuario revocó los permisos.
                $errorBody = json_decode($e->getMessage(), true);
                if (isset($errorBody['error']) && $errorBody['error'] == 'invalid_grant') {
                    throw new InvalidGoogleTokenException('El token de Google fue revocado o es inválido.');
                }
                // Para cualquier otro error, lo relanzamos.
                throw $e;
            }
        }
        return $this->client;
    }

    /**
     * Crea un nuevo evento en el calendario principal del usuario.
     *
     * @param Appointment $appointment La cita a sincronizar.
     * @param User $user El profesional dueño del calendario.
     */
    public function createEvent(Appointment $appointment, User $user): void
    {
        $client = $this->getAuthenticatedClient($user);
        $calendarService = new GoogleCalendar($client);

        $event = new GoogleCalendarEvent([
            'summary' => $appointment->title,
            'description' => 'Cita agendada a través de tu plataforma.',
            'start' => ['dateTime' => (new \DateTime($appointment->start))->format(\DateTime::RFC3339), 'timeZone' => config('app.timezone')],
            'end' => ['dateTime' => (new \DateTime($appointment->end))->format(\DateTime::RFC3339), 'timeZone' => config('app.timezone')],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => "meet-" . $appointment->id . "-" . time(),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
        ]);

        $options = ['conferenceDataVersion' => 1];
        $createdEvent = $calendarService->events->insert('primary', $event, $options);

        // --- ESTA ES LA ÚNICA LÍNEA QUE CAMBIA RESPECTO A MI RESPUESTA ANTERIOR ---
        $appointment->google_event_id = $createdEvent->getId();
        $appointment->link = $createdEvent->getHangoutLink(); // Guardamos en tu campo 'link'
        $appointment->save();
    }

    /**
     * Actualiza un evento existente en Google Calendar.
     *
     * @param Appointment $appointment
     * @param User $user
     */
    /**
     * Actualiza un evento existente en Google Calendar.
     *
     * @param Appointment $appointment
     * @param User $user
     */
    public function updateEvent(Appointment $appointment, User $user)
    {
        // Si la cita no tiene un ID de evento de Google, no hay nada que actualizar.
        if (!$appointment->google_event_id) {
            return;
        }

        $client = $this->getAuthenticatedClient($user);
        $calendarService = new GoogleCalendar($client);

        try {
            // Obtenemos el evento existente de Google.
            $event = $calendarService->events->get('primary', $appointment->google_event_id);

            // 1. Actualizamos el título (resumen)
            $event->setSummary($appointment->title);

            // 2. Creamos y configuramos el objeto para la fecha de inicio
            $start = new \Google_Service_Calendar_EventDateTime();
            $start->setDateTime((new \DateTime($appointment->start))->format(\DateTime::RFC3339));
            $start->setTimeZone(config('app.timezone'));
            $event->setStart($start);

            // 3. Creamos y configuramos el objeto para la fecha de fin
            $end = new \Google_Service_Calendar_EventDateTime();
            $end->setDateTime((new \DateTime($appointment->end))->format(\DateTime::RFC3339));
            $end->setTimeZone(config('app.timezone'));
            $event->setEnd($end);

            // FIN DE LA CORRECCIÓN

            return $calendarService->events->update('primary', $event->getId(), $event);
        } catch (\Google\Service\Exception $e) {
            // Si el evento no se encuentra (Error 404), desvinculamos el evento.
            if ($e->getCode() == 404) {
                $appointment->google_event_id = null;
                $appointment->save();
                Log::info("El evento de Google {$appointment->google_event_id} no fue encontrado. Se desvinculó de la cita {$appointment->id}.");
            } else {
                throw $e; // Relanzamos cualquier otra excepción.
            }
        }
    }

    /**
     * Elimina un evento de Google Calendar.
     *
     * @param string|null $googleEventId El ID del evento a eliminar.
     * @param User $user
     */
    public function deleteEvent(?string $googleEventId, User $user)
    {
        // Si no hay ID, no hay nada que borrar.
        if (!$googleEventId) {
            return;
        }

        $client = $this->getAuthenticatedClient($user);
        $calendarService = new GoogleCalendar($client);

        try {
            $calendarService->events->delete('primary', $googleEventId);
        } catch (\Google\Service\Exception $e) {
            // Si el evento ya no existe (404), nuestro objetivo está cumplido. Ignoramos el error.
            if ($e->getCode() != 404) {
                throw $e; // Relanzamos cualquier otro error.
            }
        }
    }
}
