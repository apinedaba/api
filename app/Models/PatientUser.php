<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Patient_Medication;
class PatientUser extends Model
{
    use HasFactory;
    protected $fillable = [
        'user',
        'patient',
        'activo',
        'status'
    ];

    protected $hidden =["created_at", "updated_at","user"];

    public function patient() {
        return $this->belongsTo(Patient::class, 'patient', 'id');
    }

    public function medications()
    {
        return $this->hasMany(Patient_Medication::class, 'patient_id', 'patient');
    }
}
