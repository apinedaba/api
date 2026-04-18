<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Vendedor extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
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

    public function referrals(): HasMany
    {
        return $this->hasMany(SellerReferral::class);
    }

    public function commissionItems(): HasMany
    {
        return $this->hasMany(SellerCommissionItem::class);
    }
}
