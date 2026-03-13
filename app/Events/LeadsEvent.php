<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class LeadsEvent implements ShouldBroadcastNow
{

    public $user;
    public $object;
    /**
     * Create a new event instance.
     */
    public function __construct($user, $object)
    {
        $this->user = $user;
        $this->object = $object;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->user->id),
        ];
    }

    public function broadcastAs()
    {
        return 'leads';
    }
}
