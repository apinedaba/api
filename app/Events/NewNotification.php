<?php

namespace App\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Broadcasting\PrivateChannel;

class NewNotification implements ShouldBroadcastNow
{
    public $channel;
    public $message;
    public $notification;

    public function __construct(string $channel, string $message, ?array $notification = null)
    {
        $this->channel = $channel;
        $this->message = $message;
        $this->notification = $notification;
    }

    public function broadcastOn()
    {
        return new PrivateChannel($this->channel);
    }

    public function broadcastAs()
    {
        return "new-notification";
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'notification' => $this->notification,
        ];
    }
}
