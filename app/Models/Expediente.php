<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Patient;
use App\Models\User;

class Expediente extends Model
{
    use HasFactory;
    protected $fillable = [
        'patient_id',
        'user_id',
        'escalas',
        'linea_vida',
        'diagnostico',
        'firma',
    ];
    protected $casts = [
        'escalas' => 'array',
        'linea_vida' => 'array',
        'diagnostico' => 'string',
        'firma' => 'string',
    ];
    public function paciente()
    {
        return $this->belongsTo(Patient::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
