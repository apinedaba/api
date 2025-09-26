<?php
// app/Models/ContractSignature.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;

class ContractSignature extends Model {
  use UsesUuid;

  protected $table = 'contract_signatures';

  protected $fillable = [
    'contract_instance_id','signer_type','signature_public_id','signature_url',
    'signed_at','signer_ip','signer_ua'
  ];

  protected $casts = [
    'signed_at' => 'datetime',
  ];
}