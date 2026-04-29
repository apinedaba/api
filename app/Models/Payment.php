<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payer_type',
        'appointment_id',
        'patient_id',
        'amount',
        'currency',
        'payment_method',
        'status',
        'stripe_payment_id',
        'receipt_url',
        'id_transaccion_reembolsada',
        'concepto',
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
   

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    protected $casts = [
        'amount' => 'float',
        'session_base_amount' => 'float',
        'charge_subtotal_amount' => 'float',
        'platform_fee_rate' => 'float',
        'platform_fee_amount' => 'float',
        'total_charge_amount' => 'float',
        'psychologist_amount' => 'float',
        'remaining_balance_amount' => 'float',
    ];


}
