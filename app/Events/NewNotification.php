<?php

namespace App\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Broadcasting\PrivateChannel;

class NewNotification implements ShouldBroadcastNow
{
    public $userId;
    public $message;

    public function __construct($userId, $message)
    {
        $this->userId = $userId;
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("user.{$this->userId}");
    }

    public function broadcastAs()
    {
        return "new-notification";
    }
}