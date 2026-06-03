<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'user_id',
        'fecha',
        'hora',
        'tipoSesion',
        'duracion',
        'precio',
        'estado',
        'formato', // Nueva columna agregada
        'payment_intent_id',
        'appointment_id',
        'discount',
        'discountType',
        'originalPrice',
        'categoria',
        'session_base_amount',
        'charge_subtotal_amount',
        'platform_fee_rate',
        'platform_fee_amount',
        'total_charge_amount',
        'psychologist_amount',
        'remaining_balance_amount',
        'charge_mode',
        'payout_status',

    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected $casts = [
        'session_base_amount' => 'float',
        'charge_subtotal_amount' => 'float',
        'platform_fee_rate' => 'float',
        'platform_fee_amount' => 'float',
        'total_charge_amount' => 'float',
        'psychologist_amount' => 'float',
        'remaining_balance_amount' => 'float',
    ];
}
