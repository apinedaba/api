<?php
// app/Models/ContractInstance.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;

class ContractInstance extends Model {
  use UsesUuid;

  protected $table = 'contract_instances';

  protected $fillable = [
    'template_id','patient_id','professional_id','filled_html','data_snapshot','status',
    'signed_pdf_public_id','signed_pdf_url','evidence_hash','evidence_json','expires_at'
  ];

  protected $casts = [
    'data_snapshot' => 'array',
    'evidence_json' => 'array',
    'expires_at'    => 'datetime',
  ];

  public function signatures(){ return $this->hasMany(ContractSignature::class, 'contract_instance_id'); }
  public function files(){ return $this->hasMany(ContractFile::class, 'contract_instance_id'); }
}