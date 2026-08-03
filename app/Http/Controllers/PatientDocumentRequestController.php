<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientDocumentRequest;
use App\Models\PatientUser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PatientDocumentRequestController extends Controller
{
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
        $representative = collect($patient->relationships ?? [])
            ->first(fn ($relationship) => filter_var(data_get($relationship, 'es_representante_legal'), FILTER_VALIDATE_BOOLEAN));
        if ($isMinorAuthorization) {
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
        if ($document->template_id === 'minor-therapy-authorization') {
            $validated['signer_name'] = $document->signer_name;
        }
        $document->update([...$validated, 'status' => 'signed', 'signed_at' => now()]);
        return response()->json(['message' => 'Documento firmado correctamente.', 'document' => $this->publicPayload($document->fresh())]);
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
                'professional_signature_data_url' => $document->getRawOriginal('professional_signature_data_url'),
                'signed_at' => $document->signed_at,
            ],
            'patient' => ['name' => $document->patient?->name],
            'professional' => ['name' => $document->professional?->name, 'image' => $document->professional?->image],
        ];
    }
}
