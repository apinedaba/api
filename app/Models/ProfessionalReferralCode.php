<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalReferralCode extends Model
{
    protected $fillable = ['user_id', 'code', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }
    public function referrals() { return $this->hasMany(ProfessionalReferral::class, 'referral_code_id'); }
}
