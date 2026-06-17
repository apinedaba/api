<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StripeTransactionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payment_id',
        'professional_withdrawal_id',
        'event_type',
        'direction',
        'stripe_object_type',
        'stripe_object_id',
        'status',
        'amount',
        'currency',
        'payload',
        'error',
    ];

    protected $casts = [
        'amount' => 'float',
        'payload' => 'array',
        'error' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(ProfessionalWithdrawal::class, 'professional_withdrawal_id');
    }
}
