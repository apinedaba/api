<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiExerciseGeneration extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'patient_id',
        'mode',
        'model',
        'request_payload',
        'response_payload',
        'token_usage',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'token_usage' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
