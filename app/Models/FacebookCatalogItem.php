<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookCatalogItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_enabled',
        'custom_title',
        'custom_description',
        'custom_price',
        'custom_currency',
        'custom_therapy_type',
        'custom_certification',
        'custom_image_url',
        'custom_public_url',
        'custom_schedule_summary',
        'custom_availability',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'custom_price' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
