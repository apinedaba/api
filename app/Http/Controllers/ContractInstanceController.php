<?php
// app/Http/Controllers/ContractInstanceController.php
namespace App\Http\Controllers;

use App\Models\{ContractTemplate, ContractInstance, ContractSignature, ContractFile, User};
use App\Services\{TagMergeService, ContractPdfService};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContractInstanceController extends Controller
{
  public function store(Request $r, TagMergeService $merger) {
    $data = $r->validate([
      'template_id'    => 'required|uuid|exists:contract_templates,id',
      'patient_id'     => 'required|exists:users,id',
      'professional_id'=> 'required|exists:users,id',
      'expires_at'     => 'nullable|date'
    ]);

    $tpl = ContractTemplate::findOrFail($data['template_id']);
    $patient = User::findOrFail($data['patient_id']);
    $pro     = User::findOrFail($data['professional_id']);

    $dataset = [
      'paciente'=>[
        'nombre'=>$patient->name,
        'telefono'=>$patient->phone ?? null,
        'fecha_nacimiento'=>$patient->birthdate ?? null,
      ],
      'profesional'=>[
        'nombre'=>$pro->name,
        'cedula'=>$pro->cedula ?? null,
      ],
      'fecha'=>['hoy'=>now()->toDateString()],
      'contrato'=>['folio'=>Str::upper(Str::random(10))]
    ];

    $required = collect($tpl->tags_schema ?? [])->where('required',true)->pluck('key')->all();
    [$ok, $filled, $missing] = $merger->merge($tpl->html, $dataset, $required);

    if (!$ok) return response()->json(['message'=>'Faltan datos requeridos','missing'=>$missing], 422);

    $instance = ContractInstance::create([
      'id'=>Str::uuid(),
      'template_id'=>$tpl->id,
      'patient_id'=>$patient->id,
      'professional_id'=>$pro->id,
      'filled_html'=>$filled,
      'data_snapshot'=>$dataset,
      'status'=>'draft',
      'expires_at'=>$data['expires_at'] ?? null
    ]);

    return response()->json($instance, 201);
  }

  public function send(Request $r, ContractInstance $instance) {
    // $this->authorize('update', $instance);
    abort_if($instance->status!=='draft', 422, 'Solo puedes enviar contratos en borrador.');
    $instance->status = 'sent';
    $instance->save();

    // TODO: notificación email/push al paciente
    return $instance;
  }

  public function show(Request $r, ContractInstance $instance) {
    // $this->authorize('view', $instance);
    if ($instance->status==='sent') {
      $instance->status = 'viewed';
      $instance->save();
    }
    return $instance->load('signatures','files');
  }

  // Firma (Camino 1)
  public function signature(Request $r, ContractInstance $instance, ContractPdfService $pdfSvc) {
    // $this->authorize('update', $instance);
    $data = $r->validate([
      'dataUrl_png'=>'required|string',
      'signer_type'=>['required', Rule::in(['patient','professional'])]
    ]);
    abort_if(!in_array($instance->status, ['sent','viewed']), 422, 'Estado inválido para firmar.');

    $sig = $pdfSvc->uploadSignatureDataUrl($data['dataUrl_png']);
    $signature = ContractSignature::create([
      'id'=>Str::uuid(),
      'contract_instance_id'=>$instance->id,
      'signer_type'=>$data['signer_type'],
      'signature_public_id'=>$sig['public_id'],
      'signature_url'=>$sig['secure_url'],
      'signed_at'=>now(),
      'signer_ip'=>$r->ip(),
      'signer_ua'=>$r->userAgent(),
    ]);

    return response()->json($signature, 201);
  }

  // Finalizar (genera PDF y cierra)
  public function finalize(Request $r, ContractInstance $instance, ContractPdfService $pdfSvc) {
    // $this->authorize('update', $instance);
    abort_if(!in_array($instance->status, ['viewed','sent']), 422, 'Primero ver/firmar.');
    $patientSig = $instance->signatures()->where('signer_type','patient')->latest()->first();
    abort_if(!$patientSig, 422, 'Falta la firma del paciente.');

    $res = $pdfSvc->renderAndUpload(
      finalHtml: $instance->filled_html,
      signatureUrl: $patientSig->signature_url,
      options: ['folder'=>'mindmeet/contracts/signed']
    );

    $evidence = [
      'finalized_at'=>now()->toIso8601String(),
      'patient_signature_public_id'=>$patientSig->signature_public_id,
      'patient_signature_signed_at'=>$patientSig->signed_at?->toIso8601String(),
      'client_ip'=>$r->ip(),
      'user_agent'=>$r->userAgent(),
    ];

    $instance->update([
      'status'=>'signed',
      'signed_pdf_public_id'=>$res['public_id'],
      'signed_pdf_url'=>$res['secure_url'],
      'evidence_hash'=>$res['sha256'],
      'evidence_json'=>$evidence,
    ]);

    return $instance;
  }

  // Upload firmado externo (Camino 2)
  public function upload(Request $r, ContractInstance $instance, ContractPdfService $pdfSvc) {
    // $this->authorize('update', $instance);
    abort_if(!in_array($instance->status, ['sent','viewed','uploaded_by_patient']), 422);
    $data = $r->validate([
      'file'=>'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
      'notes'=>'nullable|string'
    ]);

    $local = $data['file']->getRealPath();
    $ext   = strtolower($data['file']->getClientOriginalExtension());
    if ($ext === 'pdf') {
      $up = $pdfSvc->uploadPdfFile($local, 'mindmeet/contracts/uploads');
    } else {
      $upR = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::uploadFile($local, [
        'folder'=>'mindmeet/contracts/uploads', 'resource_type'=>'image',
        'use_filename'=>true, 'unique_filename'=>true, 'overwrite'=>false,
      ]);
      $up = ['public_id'=>$upR->getPublicId(), 'secure_url'=>$upR->getSecurePath()];
    }

    $file = ContractFile::create([
      'id'=>Str::uuid(),
      'contract_instance_id'=>$instance->id,
      'type'=>'patient_signed_upload',
      'file_public_id'=>$up['public_id'],
      'file_url'=>$up['secure_url'],
      'uploaded_by'=>$r->user()->id,
      'uploaded_at'=>now(),
      'notes'=>$data['notes'] ?? null,
    ]);

    if ($instance->status!=='uploaded_by_patient') {
      $instance->status='uploaded_by_patient';
      $instance->save();
    }

    return response()->json($file, 201);
  }
}
