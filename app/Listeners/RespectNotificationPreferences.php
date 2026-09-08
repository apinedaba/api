<?php

namespace App\Listeners;

use App\Services\NotificationPreferenceService;
use Illuminate\Notifications\Events\NotificationSending;

class RespectNotificationPreferences
{
    public function __construct(private NotificationPreferenceService $preferences) {}
    public function handle(NotificationSending $event): bool
    {
        return $this->preferences->enabled($event->notifiable, $event->notification, $event->channel);
    }
}
