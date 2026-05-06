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
        'upcoming_charge_notified_for_date',
        'last_payment_failed_invoice_id',
        'last_payment_failed_notified_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_reminder_day_1_at' => 'datetime',
        'trial_reminder_day_3_at' => 'datetime',
        'trial_reminder_day_7_at' => 'datetime',
        'upcoming_charge_notified_for_date' => 'date',
        'last_payment_failed_notified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
