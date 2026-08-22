<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalReferral extends Model
{
    protected $fillable = ['inviter_user_id', 'invited_user_id', 'referral_code_id', 'status', 'registered_at', 'first_paid_at', 'first_paid_invoice_id'];
    protected $casts = ['registered_at' => 'datetime', 'first_paid_at' => 'datetime'];

    public function inviter() { return $this->belongsTo(User::class, 'inviter_user_id'); }
    public function invited() { return $this->belongsTo(User::class, 'invited_user_id'); }
    public function code() { return $this->belongsTo(ProfessionalReferralCode::class, 'referral_code_id'); }
    public function reward() { return $this->hasOne(ProfessionalReferralReward::class); }
}
