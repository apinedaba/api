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
        'user_id',
        'lead_type',
        'session_package_id',
        'package_name',
        'package_total_price',
        'package_session_price',
        'package_session_count',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sessionPackage()
    {
        return $this->belongsTo(SessionPackage::class);
    }
}
