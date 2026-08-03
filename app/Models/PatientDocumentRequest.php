<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientDocumentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id', 'user_id', 'organization_id', 'template_id', 'public_token',
        'title', 'content', 'requires_signature', 'professional_signature_data_url',
        'status', 'signer_name', 'signer_role', 'signature_data_url', 'expires_at', 'signed_at',
    ];

    protected $hidden = ['public_token', 'signature_data_url', 'professional_signature_data_url'];

    protected $casts = [
        'requires_signature' => 'boolean',
        'expires_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function patient() { return $this->belongsTo(Patient::class); }
    public function professional() { return $this->belongsTo(User::class, 'user_id'); }
}
