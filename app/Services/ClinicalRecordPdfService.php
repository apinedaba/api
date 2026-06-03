<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Expediente;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\Patient_Medication;
use App\Models\Sintomas;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;

class ClinicalRecordPdfService
{
    private const DEFAULT_LOGO = 'https://res.cloudinary.com/dabwvv94x/image/upload/v1764650408/MindMeet_1280_x_350_px_c6yojr.png';

    public function render(User $user, Patient $patient): string
    {
        $relation = PatientUser::where('user', $user->id)
            ->where('patient', $patient->id)
            ->firstOrFail();

        $expediente = Expediente::where('user_id', $user->id)
            ->where('patient_id', $patient->id)
            ->first();

        $sessions = Appointment::where('user', $user->id)
            ->where('patient', $patient->id)
            ->with([
                'notes' => fn ($query) => $query->orderBy('created_at', 'desc'),
                'attachments',
            ])
            ->orderBy('start', 'desc')
            ->get();

        $medications = Patient_Medication::where('patient_id', $patient->id)
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')->orWhere('user_id', $user->id);
            })
            ->orderByDesc('created_at')
            ->get();

        $symptoms = $this->resolveSymptoms($user, $patient);

        $html = view('pdf.clinical-record', [
            'generatedAt' => now(),
            'user' => $user,
            'patient' => $patient,
            'relation' => $relation,
            'expediente' => $expediente,
            'sessions' => $sessions,
            'medications' => $medications,
            'symptoms' => $symptoms,
            'logoUrl' => data_get($user->configurations, 'expediente_logo_url') ?: self::DEFAULT_LOGO,
            'professionalSchool' => $this->professionalSchool($user),
            'mentalLabels' => $this->mentalLabels(),
        ])->render();

        $browsershot = Browsershot::html($html)
            ->format('A4')
            ->margins(12, 12, 14, 12)
            ->showBackground()
            ->noSandbox()
            ->addChromiumArguments([
                'disable-dev-shm-usage',
                'disable-gpu',
                'user-data-dir' => storage_path('app/browsershot/chrome-profile'),
            ]);

        $this->configureBrowsershot($browsershot);

        return $browsershot->pdf();
    }

    public function filename(Patient $patient): string
    {
        return 'expediente-' . Str::slug($patient->name ?: 'paciente') . '-' . now()->format('Ymd') . '.pdf';
    }

    private function professionalSchool(User $user): ?array
    {
        $schools = data_get($user->educacion, 'escuelas', []);

        if (!is_array($schools)) {
            return null;
        }

        foreach ($schools as $school) {
            if (data_get($school, 'profesion') || data_get($school, 'cedula')) {
                return $school;
            }
        }

        return $schools[0] ?? null;
    }

    private function resolveSymptoms(User $user, Patient $patient): array
    {
        $row = Sintomas::where('psicologo_id', $user->id)
            ->where('paciente_id', $patient->id)
            ->first();

        $raw = $row?->sintoma;

        if (!$raw) {
            return [];
        }

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function mentalLabels(): array
    {
        return [
            'apariencia_conducta' => 'Apariencia y conducta',
            'orientacion' => 'Orientacion',
            'atencion_memoria' => 'Atencion y memoria',
            'lenguaje' => 'Lenguaje',
            'afecto_animo' => 'Afecto y animo',
            'pensamiento_juicio' => 'Pensamiento y juicio',
            'autoconcepto_personalidad' => 'Autoconcepto y personalidad',
        ];
    }

    private function configureBrowsershot(Browsershot $browsershot): void
    {
        $chromePath = config('services.browsershot.chrome_path');
        $nodeBinary = config('services.browsershot.node_binary');
        $npmBinary = config('services.browsershot.npm_binary');
        $nodeModulePath = config('services.browsershot.node_module_path');
        $includePath = config('services.browsershot.include_path');

        if ($chromePath) {
            $browsershot->setChromePath($chromePath);
        }

        if ($nodeBinary) {
            $browsershot->setNodeBinary($nodeBinary);
        }

        if ($npmBinary) {
            $browsershot->setNpmBinary($npmBinary);
        }

        if ($nodeModulePath) {
            $browsershot->setNodeModulePath($nodeModulePath);
        }

        if ($includePath) {
            $browsershot->setIncludePath($includePath);
        }
    }
}
