<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'psychologist_id',
        'content',
        'type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Nota pertenece a una sesión
    public function session()
    {
        return $this->belongsTo(Sesion::class, 'session_id');
    }

    // Nota pertenece a un psicólogo
    public function psicologo()
    {
        return $this->belongsTo(User::class, 'psychologist_id');
    }

    // Accesor ejemplo: limitar texto si quieres mostrar un preview
    public function getPreviewAttribute()
    {
        return strip_tags(substr($this->content, 0, 120)) . '...';
    }
}
