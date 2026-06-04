<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'user_id',
        'role',
        'is_primary',
        'can_manage_schedule',
        'can_manage_patients',
        'can_view_finance',
        'meta',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'can_manage_schedule' => 'boolean',
        'can_manage_patients' => 'boolean',
        'can_view_finance' => 'boolean',
        'meta' => 'array',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
