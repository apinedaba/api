<?php

namespace App\Events;

use App\Models\MinderMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MinderMessageSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public MinderMessage $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('minder.group.' . $this->message->group_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'minder-message';
    }

    public function broadcastWith(): array
    {
        $user = $this->message->user;
        return [
            'message' => [
                'id'         => $this->message->id,
                'group_id'   => $this->message->group_id,
                'parent_id'  => $this->message->parent_id,
                'body'       => $this->message->body,
                'is_deleted' => $this->message->is_deleted,
                'created_at' => $this->message->created_at,
                'user'       => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'image' => $user->image,
                ],
                'reactions'       => [],
                'replies_count'   => 0,
            ],
        ];
    }
}
