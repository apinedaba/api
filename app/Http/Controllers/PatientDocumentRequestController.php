<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientDocumentRequest;
use App\Models\PatientUser;
use App\Notifications\PatientDocumentAssignedNotification;
use App\Notifications\ProfessionalDocumentSignedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class PatientDocumentRequestController extends Controller
{
    public function patientIndex(Request $request)
    {
        return response()->json(PatientDocumentRequest::where('patient_id', $request->user()->id)
            ->whereIn('status', ['pending', 'signed', 'delivered'])
            ->latest()->get()->map(fn ($document) => $this->professionalPayload($document)));
    }

    public function index(Request $request, Patient $patient)
    {
        $this->authorizePatient($request, $patient);

        return response()->json(PatientDocumentRequest::where('patient_id', $patient->id)
            ->where('user_id', $request->user()->id)
            ->latest()->get()->map(fn ($document) => $this->professionalPayload($document)));
    }

    public function store(Request $request, Patient $patient)
    {
        $this->authorizePatient($request, $patient);
        $validated = $request->validate([
            'template_id' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:160'],
            'content' => ['required', 'string', 'max:30000'],
            'requires_signature' => ['required', 'boolean'],
            'signer_name' => ['required_if:requires_signature,true', 'nullable', 'string', 'max:160'],
            'signer_role' => ['nullable', 'string', 'max:100'],
        ]);
        $preferences = data_get($request->user()->configurations, 'document_preferences', []);
        $isMinorAuthorization = ($validated['template_id'] ?? null) === 'minor-therapy-authorization';
        $birthDate = data_get($patient->relevantes, 'fechaNac');
        $isMinor = $birthDate && now()->diffInYears($birthDate) < 18;
        $representative = collect($patient->relationships ?? [])
            ->first(fn ($relationship) => filter_var(data_get($relationship, 'es_representante_legal'), FILTER_VALIDATE_BOOLEAN));
        if ($validated['requires_signature'] && ($isMinor || $isMinorAuthorization)) {
            abort_unless($representative && filled(data_get($representative, 'nombre')), 422, 'Registra primero al representante legal del menor.');
            $validated['signer_name'] = data_get($representative, 'nombre');
            $validated['signer_role'] = data_get($representative, 'parentesco') ?: 'Representante legal';
        }
        $document = PatientDocumentRequest::create([
            ...$validated,
            'patient_id' => $patient->id,
            'user_id' => $request->user()->id,
            'organization_id' => $request->attributes->get('active_organization')?->id ?: $patient->organization_id,
            'public_token' => Str::random(72),
            'professional_signature_data_url' => $validated['requires_signature']
                ? data_get($preferences, 'professional_signature_data_url') : null,
            'status' => $validated['requires_signature'] ? 'pending' : 'delivered',
            'expires_at' => now()->addDays(30),
        ]);

        $notification = new PatientDocumentAssignedNotification($document, $request->user());
        $patient->notify($notification);

        return response()->json($this->professionalPayload($document), 201);
    }

    public function cancel(Request $request, Patient $patient, PatientDocumentRequest $documentRequest)
    {
        $this->authorizePatient($request, $patient);
        abort_unless($documentRequest->patient_id === $patient->id && $documentRequest->user_id === $request->user()->id, 404);
        abort_if($documentRequest->status === 'signed', 422, 'Un documento firmado no puede cancelarse.');
        $documentRequest->update(['status' => 'cancelled']);
        return response()->json($this->professionalPayload($documentRequest->fresh()));
    }

    public function showPublic(string $token)
    {
        $document = PatientDocumentRequest::with(['patient', 'professional'])->where('public_token', $token)->firstOrFail();
        if ($document->expires_at?->isPast() && $document->status === 'pending') {
            abort(410, 'Este enlace venció. Solicita uno nuevo a tu profesional.');
        }
        abort_if($document->status === 'cancelled', 410, 'Esta solicitud fue cancelada.');

        return response()->json($this->publicPayload($document));
    }

    public function signPublic(Request $request, string $token)
    {
        $document = PatientDocumentRequest::where('public_token', $token)->firstOrFail();
        abort_unless($document->status === 'pending', 422, 'Este documento ya no está pendiente de firma.');
        abort_if($document->expires_at?->isPast(), 410, 'Este enlace venció. Solicita uno nuevo a tu profesional.');
        $validated = $request->validate([
            'signer_name' => ['required', 'string', 'max:160'],
            'signature_data_url' => ['required', 'string', 'max:2000000'],
        ]);
        $validated['signer_name'] = $document->signer_name;
        $document->update([...$validated, 'status' => 'signed', 'signed_at' => now()]);
        $document->load(['patient', 'professional']);
        if ($document->professional) {
            $notification = new ProfessionalDocumentSignedNotification($document, $document->patient);
            $document->professional->notify($notification);
        }
        return response()->json(['message' => 'Documento firmado correctamente.', 'document' => $this->publicPayload($document->fresh())]);
    }

    public function publicPdf(string $token)
    {
        $document = PatientDocumentRequest::with(['patient', 'professional'])->where('public_token', $token)->firstOrFail();
        abort_unless($document->status === 'signed', 409, 'El documento todavía no ha sido firmado.');

        $pdf = Pdf::loadHTML($this->signedPdfHtml($document))->setPaper('a4');
        return $pdf->stream(Str::slug($document->title ?: 'documento-firmado').'.pdf');
    }

    private function authorizePatient(Request $request, Patient $patient): void
    {
        abort_unless(PatientUser::where('patient', $patient->id)->where('user', $request->user()->id)->exists(), 403, 'El paciente no pertenece al profesional.');
    }

    private function professionalPayload(PatientDocumentRequest $document): array
    {
        return [...$document->toArray(), 'public_url' => rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://app.mindmeet.com.mx')), '/') . '/documento/' . $document->getRawOriginal('public_token')];
    }

    private function publicPayload(PatientDocumentRequest $document): array
    {
        $document->loadMissing(['patient', 'professional']);
        return [
            'document' => [
                'title' => $document->title, 'content' => $document->content,
                'requires_signature' => $document->requires_signature, 'status' => $document->status,
                'signer_name' => $document->signer_name, 'signer_role' => $document->signer_role,
                'signed_at' => $document->signed_at,
                'pdf_url' => $document->status === 'signed'
                    ? url('/api/public/documents/'.$document->getRawOriginal('public_token').'/pdf') : null,
            ],
            'patient' => ['name' => $document->patient?->name],
            'professional' => ['name' => $document->professional?->name, 'image' => $document->professional?->image],
        ];
    }

    private function signedPdfHtml(PatientDocumentRequest $document): string
    {
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $document->content ?: '');
        $patientSignature = $this->safeSignature($document->getRawOriginal('signature_data_url'));
        $professionalSignature = $this->safeSignature($document->getRawOriginal('professional_signature_data_url'));
        $signedAt = $document->signed_at?->timezone('America/Mexico_City')->format('d/m/Y H:i') ?: '';

        return '<!doctype html><html><head><meta charset="utf-8"><style>
            @page{margin:34px 42px}body{font-family:DejaVu Sans,Arial,sans-serif;color:#1e293b;font-size:11px;line-height:1.65}
            .header{border-bottom:2px solid #087ca7;padding-bottom:14px;margin-bottom:24px}.brand{font-size:22px;font-weight:700;color:#087ca7}.meta{color:#64748b;margin-top:5px}
            h1{font-size:20px;color:#0f172a}.content{font-size:12px}.signatures{width:100%;margin-top:42px}.signature{width:48%;display:inline-block;text-align:center;vertical-align:top}.signature img{max-width:220px;max-height:90px}.line{border-top:1px solid #64748b;padding-top:7px;margin:8px 14px 0}.footer{margin-top:36px;border-top:1px solid #cbd5e1;padding-top:10px;color:#64748b;font-size:9px}
        </style></head><body><div class="header"><div class="brand">MindMeet</div><div class="meta">Documento: '.e($document->title).' · Paciente: '.e($document->patient?->name).' · Fecha de firma: '.e($signedAt).'</div></div><h1>'.e($document->title).'</h1><div class="content">'.$content.'</div><div class="signatures"><div class="signature">'.$patientSignature.'<div class="line">'.e($document->signer_name).'<br>'.e($document->signer_role ?: 'Paciente').'</div></div><div class="signature">'.$professionalSignature.'<div class="line">'.e($document->professional?->name).'<br>Profesional</div></div></div><div class="footer">Emitido y firmado digitalmente mediante MindMeet.</div></body></html>';
    }

    private function safeSignature(?string $value): string
    {
        return $value && str_starts_with($value, 'data:image/')
            ? '<img src="'.e($value).'" alt="Firma">'
            : '<div style="height:90px"></div>';
    }

}
