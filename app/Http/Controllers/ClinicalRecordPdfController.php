<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientUser;
use App\Services\ClinicalRecordPdfService;
use Illuminate\Http\Request;

class ClinicalRecordPdfController extends Controller
{
    public function show(Request $request, Patient $patient, ClinicalRecordPdfService $service)
    {
        $user = $request->user();

        PatientUser::where('user', $user->id)
            ->where('patient', $patient->id)
            ->firstOrFail();

        if (!$this->hasValidConsent($patient->consentimiento)) {
            return response()->json([
                'message' => 'Falta consentimiento informado firmado. Completa esta tarea antes de imprimir el expediente.',
                'type' => 'error',
            ], 422);
        }

        $pdf = $service->render($user, $patient);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $service->filename($patient) . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function hasValidConsent(?array $consent): bool
    {
        return in_array(data_get($consent, 'status'), ['signed', 'uploaded', 'physical'], true);
    }
}
