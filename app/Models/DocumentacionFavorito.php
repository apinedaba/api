<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentacionFavorito extends Model
{
    protected $table = 'documentacion_favoritos';

    protected $fillable = [
        'psicologo_id',
        'drive_id',
    ];

    public function psicologo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'psicologo_id');
    }
}
