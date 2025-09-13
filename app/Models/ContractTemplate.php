<?php
// app/Models/ContractTemplate.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;

class ContractTemplate extends Model {
  use UsesUuid;

  protected $table = 'contract_templates';

  protected $fillable = [
    'owner_type','owner_id','title','html','editable','tags_schema','version','is_active'
  ];

  protected $casts = [
    'tags_schema' => 'array',
    'is_active'   => 'boolean',
  ];
}