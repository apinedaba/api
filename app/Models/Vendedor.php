<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendedor extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'vendedores';
    protected $fillable = [
        'nombre',
        'email',
        'password',
        'telefono',
        'direccion',
        'ciudad',
        'estado',
        'codigo_postal',
        'pais',
        'rol',
        'status',
        'imagen',
        'qr_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
