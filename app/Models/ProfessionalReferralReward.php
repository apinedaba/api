<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalReferralReward extends Model
{
    public const STATUS_CREDITED = 'credited';
    public const STATUS_PAID = 'paid';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = ['professional_referral_id', 'user_id', 'amount', 'currency', 'status', 'credited_at', 'paid_at', 'source_reference', 'notes'];
    protected $casts = ['amount' => 'decimal:2', 'credited_at' => 'datetime', 'paid_at' => 'datetime'];

    public function referral() { return $this->belongsTo(ProfessionalReferral::class, 'professional_referral_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
