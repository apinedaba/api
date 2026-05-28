<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Administrator;

class ContentSectionVersion extends Model
{
    protected $table = 'content_section_versions';

    protected $fillable = [
        'content_section_id',
        'version_number',
        'data',
        'changed_by',
        'change_reason',
    ];

    protected $casts = [
        'data' => 'array',
        'version_number' => 'integer',
    ];

    /**
     * Relación: Sección de contenido
     */
    public function contentSection(): BelongsTo
    {
        return $this->belongsTo(ContentSection::class);
    }

    /**
     * Relación: Usuario administrador que realizó el cambio
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'changed_by');
    }
}
