<?php

namespace App\Listeners;

use App\Events\NewNotification;
use App\Services\Fcm;
use App\Services\NotificationPayload;
use Illuminate\Notifications\Events\NotificationSent;

class BroadcastAndPushDatabaseNotification
{
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
            broadcast(new NewNotification(
                channel: $channel,
                message: $payload['body'] ?: $payload['title'],
                notification: $payload
            ));
        }

        if (!method_exists($event->notifiable, 'deviceTokens')) {
            return;
        }

        foreach ($event->notifiable->deviceTokens()->pluck('token')->all() as $token) {
            Fcm::send($token, $payload['title'], $payload['body'], [
                'link' => $payload['action_url'] ?? '',
                'type' => $payload['kind'] ?? 'general',
                'id' => (string) $payload['id'],
            ]);
        }
    }
}
