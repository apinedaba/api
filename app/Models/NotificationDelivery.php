<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $fillable = ['notification_id', 'notifiable_type', 'notifiable_id', 'event_key', 'channel', 'status', 'idempotency_key', 'error', 'metadata', 'sent_at', 'failed_at'];
    protected $casts = ['metadata' => 'array', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];
    public function notifiable() { return $this->morphTo(); }
}
