<?php

namespace App\Listeners;

use App\Models\NotificationDelivery;
use App\Services\NotificationPreferenceService;
use Illuminate\Notifications\Events\NotificationFailed;

class AuditNotificationFailure
{
    public function __construct(private NotificationPreferenceService $preferences) {}
    public function handle(NotificationFailed $event): void
    {
        NotificationDelivery::create([
            'notifiable_type' => $event->notifiable::class, 'notifiable_id' => $event->notifiable->id,
            'event_key' => $this->preferences->eventKey($event->notification), 'channel' => $event->channel,
            'status' => 'failed', 'error' => data_get($event->data, 'exception.message', data_get($event->data, 'message')), 'metadata' => $event->data, 'failed_at' => now(),
        ]);
    }
}
