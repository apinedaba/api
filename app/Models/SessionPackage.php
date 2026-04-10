<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'session_count',
        'base_session_price',
        'package_session_price',
        'package_total_price',
        'currency',
        'formato',
        'tipo_sesion',
        'duracion',
        'categoria',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'session_count' => 'integer',
        'base_session_price' => 'decimal:2',
        'package_session_price' => 'decimal:2',
        'package_total_price' => 'decimal:2',
        'duracion' => 'integer',
        'categoria' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected $appends = [
        'base_total_price',
        'total_savings',
        'savings_percentage',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getBaseTotalPriceAttribute(): float
    {
        return round(((float) $this->base_session_price) * ((int) $this->session_count), 2);
    }

    public function getTotalSavingsAttribute(): float
    {
        return round($this->base_total_price - ((float) $this->package_total_price), 2);
    }

    public function getSavingsPercentageAttribute(): float
    {
        if ($this->base_total_price <= 0) {
            return 0;
        }

        return round(($this->total_savings / $this->base_total_price) * 100, 2);
    }
}
