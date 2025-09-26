<?php
// app/Models/ContractFile.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;

class ContractFile extends Model {
  use UsesUuid;

  protected $table = 'contract_files';

  protected $fillable = [
    'contract_instance_id','type','file_public_id','file_url','uploaded_by','uploaded_at','notes'
  ];

  protected $casts = [
    'uploaded_at' => 'datetime',
  ];
}