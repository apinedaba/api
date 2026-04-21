<?php

namespace App\Events;

use App\Models\MinderSupportMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MinderSupportMessageSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public MinderSupportMessage $supportMessage) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('minder.support.' . $this->supportMessage->thread->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'minder-support-message';
    }

    public function broadcastWith(): array
    {
        $sender = $this->supportMessage->sender;
        return [
            'message' => [
                'id'          => $this->supportMessage->id,
                'thread_id'   => $this->supportMessage->thread_id,
                'body'        => $this->supportMessage->body,
                'sender_type' => $this->supportMessage->sender_type,
                'sender_id'   => $this->supportMessage->sender_id,
                'is_read'     => $this->supportMessage->is_read,
                'created_at'  => $this->supportMessage->created_at,
                'sender'      => [
                    'id'    => $sender?->id,
                    'name'  => $sender?->name,
                    'image' => $sender?->image ?? null,
                ],
            ],
        ];
    }
}
