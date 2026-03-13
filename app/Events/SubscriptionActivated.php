<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionActivated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public $userId;
    public $message;

    public function __construct($userId, $message = "Suscripción activada")
    {
        $this->userId = $userId;
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("subscription." . $this->userId)
        ];
    }

    public function broadcastAs()
    {
        return "subscription-activated";
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'user_id' => $this->userId
        ];
    }
}