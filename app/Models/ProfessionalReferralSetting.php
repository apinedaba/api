<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalReferralSetting extends Model
{
    protected $fillable = [
        'points_enabled',
        'points_per_qualified_referral',
        'points_name',
        'points_description',
    ];

    protected $casts = [
        'points_enabled' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'points_enabled' => false,
            'points_per_qualified_referral' => 10,
            'points_name' => 'MindPoints',
            'points_description' => 'Saldo virtual para canjear por beneficios MindMeet. Cada MindPoint equivale a $1 MXN.',
        ]);
    }
}
