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
        'precio',
        'formato',
        'categoria',
        'discount',
        'discount_type',
        'codigo_descuento',
        'discount_coupon_id',
        'coupon_code',
        'coupon_discount_type',
        'coupon_discount_value',
        'subtotal_amount',
        'coupon_discount_amount',
        'final_amount',
        'lead_source',
        'lead_medium',
        'lead_campaign',
        'landing_page',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'referrer',
    ];

    protected $casts = [
        'package_total_price' => 'decimal:2',
        'package_session_price' => 'decimal:2',
        'coupon_discount_value' => 'decimal:2',
        'subtotal_amount' => 'decimal:2',
        'coupon_discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
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
