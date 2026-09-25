<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['code', 'name', 'stripe_price_id', 'stripe_lookup_key', 'is_active', 'sort_order'];
    protected $casts = ['is_active' => 'boolean'];

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'plan_features')->withPivot(['enabled', 'usage_limit'])->withTimestamps();
    }
}
