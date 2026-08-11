<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiPatientSummary extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id', 'user_id', 'patient_id', 'recipient', 'title', 'content',
        'included_sections', 'instructions', 'model', 'token_usage',
    ];

    protected $casts = [
        'included_sections' => 'array',
        'token_usage' => 'array',
    ];
}
