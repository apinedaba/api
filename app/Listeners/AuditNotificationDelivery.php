<?php

namespace App\Listeners;

use App\Models\NotificationDelivery;
use App\Services\NotificationPreferenceService;
use Illuminate\Notifications\Events\NotificationSent;

class AuditNotificationDelivery
{
    public function __construct(private NotificationPreferenceService $preferences) {}
    public function handle(NotificationSent $event): void
    {
        $id = property_exists($event->notification, 'id') ? $event->notification->id : null;
        $key = implode(':', [$event->notifiable::class, $event->notifiable->id, $this->preferences->eventKey($event->notification), $id ?: now()->format('YmdHi')]);
        NotificationDelivery::updateOrCreate(['idempotency_key' => $key, 'channel' => $event->channel], [
            'notification_id' => is_string($id) ? $id : null,
            'notifiable_type' => $event->notifiable::class,
            'notifiable_id' => $event->notifiable->id,
            'event_key' => $this->preferences->eventKey($event->notification),
            'status' => 'sent', 'sent_at' => now(),
        ]);
    }
}
