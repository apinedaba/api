<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['notifiable_type', 'notifiable_id', 'event_key', 'channels', 'quiet_hours_start', 'quiet_hours_end', 'timezone'];
    protected $casts = ['channels' => 'array'];
    public function notifiable() { return $this->morphTo(); }
}
