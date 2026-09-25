<?php

namespace App\Services;

use App\Models\MinderSupportAppointment;
use App\Models\MinderSupportGoogleAccount;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleCalendarEvent;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar\FreeBusyRequestItem;

class MinderSupportGoogleCalendarService
{
    private GoogleClient $client;

    public function __construct()
    {
        $this->client = new GoogleClient();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.calendar_redirect_uri'));
        $this->client->setScopes([
            GoogleCalendar::CALENDAR_EVENTS,
            'https://www.googleapis.com/auth/calendar.events.freebusy',
            'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
        ]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    public function authUrl(string $state): string
    {
        $this->client->setState($state);

        return $this->client->createAuthUrl();
    }

    public function connect(string $code): void
    {
        $tokens = $this->client->fetchAccessTokenWithAuthCode($code);
        if (! empty($tokens['error']) || empty($tokens['access_token'])) {
            throw new \RuntimeException($tokens['error_description'] ?? 'Google no devolvió un token válido.');
        }

        $account = MinderSupportGoogleAccount::query()->first();
        MinderSupportGoogleAccount::updateOrCreate(
            ['id' => $account?->id ?? 1],
            [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $account?->refresh_token,
                'expires_in' => $tokens['expires_in'] ?? 3600,
                'calendar_id' => $account?->calendar_id ?? 'primary',
            ],
        );
    }

    public function isConnected(): bool
    {
        return (bool) MinderSupportGoogleAccount::query()->whereNotNull('refresh_token')->exists();
    }

    /** @return array<int, array{start: Carbon, end: Carbon}> */
    public function busyIntervals(Carbon $from, Carbon $until): array
    {
        if (! $this->isConnected()) {
            return [];
        }

        $account = MinderSupportGoogleAccount::query()->firstOrFail();
        $calendarId = $account->calendar_id ?: 'primary';
        $request = new FreeBusyRequest();
        $request->setTimeMin($from->copy()->utc()->toRfc3339String());
        $request->setTimeMax($until->copy()->utc()->toRfc3339String());
        $item = new FreeBusyRequestItem();
        $item->setId($calendarId);
        $request->setItems([$item]);
        $calendar = new GoogleCalendar($this->authenticatedClient($account));
        $busy = $calendar->freebusy->query($request)->getCalendars()[$calendarId]?->getBusy() ?? [];

        return collect($busy)->map(fn ($period) => [
            'start' => Carbon::parse($period->getStart()),
            'end' => Carbon::parse($period->getEnd()),
        ])->all();
    }

    public function createEvent(MinderSupportAppointment $appointment): void
    {
        $account = MinderSupportGoogleAccount::query()->firstOrFail();
        $timezone = config('app.timezone');
        $start = $appointment->scheduled_at->copy()->timezone($timezone);
        $end = $start->copy()->addMinutes($appointment->duration_minutes);
        $event = new GoogleCalendarEvent([
            'summary' => 'Sesión de apoyo MindMeet · ' . ($appointment->user?->name ?? 'Profesional'),
            'description' => "Solicitud de apoyo en MindMeet\n\nTema: {$appointment->topic}\n\n{$appointment->description}",
            'start' => ['dateTime' => $start->toRfc3339String(), 'timeZone' => $timezone],
            'end' => ['dateTime' => $end->toRfc3339String(), 'timeZone' => $timezone],
            'attendees' => filled($appointment->user?->email) ? [['email' => $appointment->user->email]] : [],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => 'minder-support-' . $appointment->id . '-' . now()->timestamp,
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
        ]);
        $calendarId = $account->calendar_id ?: 'primary';
        $created = (new GoogleCalendar($this->authenticatedClient($account)))->events->insert($calendarId, $event, [
            'conferenceDataVersion' => 1,
            'sendUpdates' => 'all',
        ]);

        $appointment->update([
            'status' => 'confirmed',
            'meeting_url' => $created->getHangoutLink(),
            'google_event_id' => $created->getId(),
            'google_calendar_id' => $calendarId,
        ]);
    }

    public function deleteEvent(MinderSupportAppointment $appointment): void
    {
        if (! $appointment->google_event_id || ! $this->isConnected()) {
            return;
        }

        $account = MinderSupportGoogleAccount::query()->firstOrFail();
        (new GoogleCalendar($this->authenticatedClient($account)))->events->delete(
            $appointment->google_calendar_id ?: $account->calendar_id ?: 'primary',
            $appointment->google_event_id,
            ['sendUpdates' => 'all'],
        );
    }

    private function authenticatedClient(MinderSupportGoogleAccount $account): GoogleClient
    {
        $this->client->setAccessToken([
            'access_token' => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'expires_in' => $account->expires_in,
            'created' => $account->updated_at->getTimestamp(),
        ]);

        if ($this->client->isAccessTokenExpired()) {
            $tokens = $this->client->fetchAccessTokenWithRefreshToken($account->refresh_token);
            if (! empty($tokens['error'])) {
                throw new \RuntimeException('La conexión de Google Calendar debe autorizarse de nuevo.');
            }
            $account->update([
                'access_token' => $tokens['access_token'],
                'expires_in' => $tokens['expires_in'] ?? 3600,
            ]);
            $this->client->setAccessToken($tokens);
        }

        return $this->client;
    }
}
