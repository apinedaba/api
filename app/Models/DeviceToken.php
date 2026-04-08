<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'platform', 'token', 'notifiable_type', 'notifiable_id'];

    public function notifiable()
    {
        return $this->morphTo();
    }
}
