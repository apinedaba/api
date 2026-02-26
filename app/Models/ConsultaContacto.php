<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class ConsultaContacto extends Model
{
    use Notifiable;
    protected $table = 'consultas_contacto';

    protected $fillable = [
        'nombre',
        'email',
        'telefono',
        'tipo_sesion',
        'motivo',
        'fecha',
        'hora',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}