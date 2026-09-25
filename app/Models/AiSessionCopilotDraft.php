<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiSessionCopilotDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id', 'user_id', 'patient_id', 'mode', 'status',
        'input_payload', 'output_payload', 'model', 'token_usage', 'applied_at',
    ];

    protected $casts = [
        'input_payload' => 'array',
        'output_payload' => 'array',
        'token_usage' => 'array',
        'applied_at' => 'datetime',
    ];
}
