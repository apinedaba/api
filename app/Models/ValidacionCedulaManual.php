<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValidacionCedulaManual extends Model
{
    use HasFactory;

    protected $table = 'validaciones_cedula_manual';

    protected $fillable = [
        'user_id',
        'numero_cedula',
        'nombre_completo',
        'institucion',
        'carrera',
        'fecha_expedicion',
        'archivo_cedula',
        'archivo_titulo',
        'estado',
        'notas_admin',
        'fecha_revision',
        'revisado_por',
    ];

    protected $casts = [
        'fecha_expedicion' => 'date',
        'fecha_revision' => 'datetime',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function revisor()
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
