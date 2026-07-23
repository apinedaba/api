<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnDemandOffer extends Model
{
    protected $fillable = [
        'on_demand_request_id', 'professional_id', 'status', 'match_score',
        'match_reasons', 'proposed_start', 'price', 'expires_at', 'responded_at', 'meta',
    ];

    protected $casts = [
        'match_score' => 'decimal:2',
        'match_reasons' => 'array',
        'proposed_start' => 'datetime',
        'price' => 'decimal:2',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'meta' => 'array',
    ];

    public function request() { return $this->belongsTo(OnDemandRequest::class, 'on_demand_request_id'); }
    public function professional() { return $this->belongsTo(User::class, 'professional_id'); }
}
