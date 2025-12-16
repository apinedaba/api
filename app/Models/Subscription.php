<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stripe_id',
        'stripe_plan',
        'stripe_status',
        'trial_ends_at',
        'ends_at',
        'trial_reminder_day_1_at',
        'trial_reminder_day_3_at',
        'trial_reminder_day_7_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
