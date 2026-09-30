<?php

namespace App\Listeners;

use App\Events\NewNotification;
use App\Services\Fcm;
use App\Services\NotificationPayload;
use Illuminate\Notifications\Events\NotificationSent;
use App\Services\NotificationPreferenceService;
use App\Models\NotificationDelivery;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

class BroadcastAndPushDatabaseNotification
{
    public function __construct(private NotificationPreferenceService $preferences) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || !$event->response) {
            return;
        }

        $notification = $event->response;
        $payload = NotificationPayload::fromDatabaseNotification($notification);
        $channel = method_exists($event->notifiable, 'notificationBroadcastChannel')
            ? $event->notifiable->notificationBroadcastChannel()
            : null;

        if ($channel) {
            try {
                broadcast(new NewNotification(
                    channel: $channel,
                    message: $payload['body'] ?: $payload['title'],
                    notification: $payload
                ));
            } catch (BroadcastException $exception) {
                // La notificación ya quedó persistida. Una caída temporal de
                // Reverb no debe revertir la acción que originó el aviso.
                Log::warning('No se pudo emitir una notificación en tiempo real.', [
                    'channel' => $channel,
                    'notification_id' => $payload['id'],
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if (!method_exists($event->notifiable, 'deviceTokens') || !$this->preferences->enabled($event->notifiable, $event->notification, 'push')) {
            return;
        }

        foreach ($event->notifiable->deviceTokens()->pluck('token')->all() as $token) {
            $sent = Fcm::send($token, $payload['title'], $payload['body'], [
                'link' => $payload['action_url'] ?? '',
                'type' => $payload['kind'] ?? 'general',
                'id' => (string) $payload['id'],
            ]);
            NotificationDelivery::updateOrCreate([
                'idempotency_key' => "{$payload['id']}:".hash('sha256', $token), 'channel' => 'push',
            ], [
                'notification_id' => $payload['id'], 'notifiable_type' => $event->notifiable::class,
                'notifiable_id' => $event->notifiable->id, 'event_key' => $this->preferences->eventKey($event->notification),
                'status' => $sent ? 'sent' : 'failed', 'sent_at' => $sent ? now() : null,
                'failed_at' => $sent ? null : now(), 'error' => $sent ? null : 'FCM rechazó el envío.',
            ]);
        }
    }
}
