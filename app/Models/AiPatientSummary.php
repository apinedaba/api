<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiPatientSummary extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id', 'user_id', 'patient_id', 'recipient', 'purpose', 'detail_level', 'title', 'content',
        'structured_content', 'included_sections', 'instructions', 'status', 'model', 'token_usage',
    ];

    protected $casts = [
        'included_sections' => 'array',
        'structured_content' => 'array',
        'token_usage' => 'array',
    ];
}
