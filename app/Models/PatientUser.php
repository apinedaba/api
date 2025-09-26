<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Patient_Medication;
use App\Models\User;
class PatientUser extends Model
{
    use HasFactory;
    protected $fillable = [
        'user',
        'patient',
        'activo',
        'status'
    ];

    protected $hidden =["created_at", "updated_at"];

    public function patient() {
        return $this->belongsTo(Patient::class, 'patient', 'id');
    }
    public function user() {
        return $this->belongsTo(User::class, 'user', 'id');
    }

    public function medications()
    {
        return $this->hasMany(Patient_Medication::class, 'patient_id', 'patient');
    }
}
