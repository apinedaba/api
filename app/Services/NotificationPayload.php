<?php

namespace App\Services;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class NotificationPayload
{
    public static function fromDatabaseNotification(DatabaseNotification $notification): array
    {
        $payload = $notification->data ?? [];

        return [
            'id' => $notification->id,
            'title' => data_get($payload, 'title', Str::headline(class_basename($notification->type))),
            'body' => data_get($payload, 'body', ''),
            'action_url' => data_get($payload, 'action_url'),
            'action_label' => data_get($payload, 'action_label', 'Ver'),
            'kind' => data_get($payload, 'kind', 'general'),
            'type' => $notification->type,
            'is_read' => $notification->read_at !== null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_human' => $notification->created_at?->diffForHumans(),
        ];
    }
}
