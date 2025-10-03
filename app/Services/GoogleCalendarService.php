<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleCalendarEvent;

class InvalidGoogleTokenException extends \Exception {}

class GoogleCalendarService
{
    protected $client;

    public function __construct()
    {
        $this->client = new GoogleClient();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.calendar_redirect_uri'));
        $this->client->setScopes([GoogleCalendar::CALENDAR_EVENTS]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function fetchTokensWithAuthCode(string $authCode): array
    {
        return $this->client->fetchAccessTokenWithAuthCode($authCode);
    }

    public function getAuthenticatedClient(User $user): GoogleClient
    {
        if (!$user->googleAccount) {
            throw new \Exception("El usuario no tiene una cuenta de Google conectada.");
        }

        $tokens = [
            'access_token' => $user->googleAccount->access_token,
            'refresh_token' => $user->googleAccount->refresh_token,
            'expires_in' => $user->googleAccount->expires_in,
            'created' => $user->googleAccount->created_at->getTimestamp(),
        ];

        $this->client->setAccessToken($tokens);

        if ($this->client->isAccessTokenExpired()) {
            try {
                $newTokens = $this->client->fetchAccessTokenWithRefreshToken($user->googleAccount->refresh_token);
                $user->googleAccount->update([
                    'access_token' => $newTokens['access_token'],
                    'expires_in' => $newTokens['expires_in'],
                ]);
                $this->client->setAccessToken($newTokens);
            } catch (\Exception $e) {
                $errorBody = json_decode($e->getMessage(), true);
                if (isset($errorBody['error']) && $errorBody['error'] == 'invalid_grant') {
                    throw new InvalidGoogleTokenException('El token de Google fue revocado o es inválido.');
                }
                throw $e;
            }
        }
        return $this->client;
    }

    public function createEvent(Appointment $appointment, User $user)
    {
        $client = $this->getAuthenticatedClient($user);
        $calendarService = new GoogleCalendar($client);

        $event = new GoogleCalendarEvent([
            'summary' => $appointment->title,
            'description' => 'Cita agendada a través de MindMeet.',
            'start' => ['dateTime' => (new \DateTime($appointment->start))->format(\DateTime::RFC3339), 'timeZone' => config('app.timezone')],
            'end' => ['dateTime' => (new \DateTime($appointment->end))->format(\DateTime::RFC3339), 'timeZone' => config('app.timezone')],
        ]);

        return $calendarService->events->insert('primary', $event);
    }
}
