<?php
namespace App\Models;


use Illuminate\Database\Eloquent\Model;


class ContractAssignment extends Model
{
protected $table = 'contract_assignments';


protected $fillable = [
'account_id','template_id','patient_id','status','payload_json','rendered_html','signature_url','pdf_url','signed_at', 'pdf_path', 'signature_path'
];


protected $casts = [
'payload_json' => 'array',
'signed_at' => 'datetime',
];


public function template(){ return $this->belongsTo(\App\Models\ContractTemplate::class, 'template_id'); }
}