<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HelpCenterArticle extends Model
{
    public const CATEGORY_DEFINITIONS = [
        'primeros-pasos' => ['name' => 'Primeros pasos', 'emoji' => '1'],
        'activar-pacientes' => ['name' => 'Activar pacientes', 'emoji' => '2'],
        'agenda' => ['name' => 'Agenda', 'emoji' => '3'],
        'monetizacion' => ['name' => 'Monetización', 'emoji' => '4'],
        'avanzado' => ['name' => 'Avanzado', 'emoji' => '5'],
    ];

    protected $fillable = [
        'title',
        'slug',
        'category_key',
        'category_name',
        'summary',
        'body',
        'estimated_read_minutes',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'estimated_read_minutes' => 'integer',
        'sort_order' => 'integer',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public static function categoryOptions(): array
    {
        return collect(self::CATEGORY_DEFINITIONS)
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'name' => $meta['name'],
                'emoji' => $meta['emoji'],
            ])
            ->values()
            ->all();
    }
}
