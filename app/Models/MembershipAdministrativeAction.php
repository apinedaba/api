<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipAdministrativeAction extends Model
{
    protected $fillable = [
        'user_id', 'administrator_id', 'action', 'previous_status',
        'stripe_subscription_id', 'stripe_refund_id', 'refund_amount',
        'refund_currency', 'reason', 'notification_sent_at',
        'notification_error', 'metadata',
    ];

    protected $casts = [
        'notification_sent_at' => 'datetime',
        'metadata' => 'array',
    ];
}
