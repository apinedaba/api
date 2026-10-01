<?php

namespace App\Http\Controllers;

use App\Models\AiPatientSummary;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Services\ClinicalSummaryContextBuilder;
use App\Services\DeepSeekPatientSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PatientSummaryAiController extends Controller
{
    private const ALLOWED_SECTIONS = [
        'profile', 'intake', 'diagnosis', 'treatment_plan', 'medications',
        'scales', 'mental_exam', 'sessions', 'questionnaires', 'emotion_diary',
    ];

    public function __construct(private DeepSeekPatientSummaryService $deepSeek, private ClinicalSummaryContextBuilder $contextBuilder)
    {
    }

    public function index(Request $request, Patient $patient)
    {
        $this->relationship($request, $patient);

        return response()->json(['data' => AiPatientSummary::query()
            ->where('patient_id', $patient->id)
            ->where('user_id', $request->user()->id)
            ->latest()->limit(20)->get()->map(fn ($summary) => $this->serialize($summary))]);
    }

    public function generate(Request $request, Patient $patient)
    {
        $relationship = $this->relationship($request, $patient);
        abort_if($relationship->archived_at, 423, 'Reactiva al paciente para generar un resumen.');

        $validated = $request->validate([
            'recipient' => 'required|string|in:psychiatrist,family,school,patient,other',
            'purpose' => 'required|string|max:80',
            'detail' => 'nullable|string|in:brief,detailed',
            'sections' => 'required|array|min:1',
            'sections.*' => 'required|string|in:' . implode(',', self::ALLOWED_SECTIONS),
            'instructions' => 'nullable|string|max:1000',
        ]);
        $sections = array_values(array_unique($validated['sections']));

        try {
            $result = $this->deepSeek->generate(
                $this->contextBuilder->build($patient, $request->user()->id, $sections),
                [...$validated, 'sections' => $sections]
            );
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => $exception->getMessage() ?: 'No se pudo generar el resumen.'], 502);
        }

        $summary = AiPatientSummary::create([
            'organization_id' => $patient->organization_id,
            'user_id' => $request->user()->id,
            'patient_id' => $patient->id,
            'recipient' => $validated['recipient'],
            'purpose' => $validated['purpose'],
            'detail_level' => $validated['detail'] ?? 'brief',
            'title' => Str::limit($result['title'] . ' - ' . $patient->name, 180, ''),
            'content' => $result['content'],
            'structured_content' => $result['structured_content'],
            'included_sections' => $sections,
            'instructions' => $validated['instructions'] ?? null,
            'status' => 'draft',
            'model' => $result['model'],
            'token_usage' => $result['token_usage'],
        ]);

        return response()->json([
            'data' => $this->serialize($summary),
            'privacy' => 'Los datos se desidentificaron antes de enviarse a Adel.',
        ], 201);
    }

    public function update(Request $request, Patient $patient, AiPatientSummary $summary)
    {
        $this->relationship($request, $patient);
        abort_unless($summary->patient_id === $patient->id && $summary->user_id === $request->user()->id, 404);
        $validated = $request->validate([
            'title' => 'required|string|max:180',
            'content' => 'required|string|max:30000',
            'structured_content' => 'nullable|array',
            'status' => 'nullable|in:draft,final',
        ]);
        $summary->update($validated);

        return response()->json(['data' => $this->serialize($summary->fresh())]);
    }

    private function relationship(Request $request, Patient $patient): PatientUser
    {
        return PatientUser::where('patient', $patient->id)->where('user', $request->user()->id)->firstOrFail();
    }


    private function serialize(AiPatientSummary $summary): array
    {
        return [
            'id' => $summary->id,
            'recipient' => $summary->recipient,
            'purpose' => $summary->purpose,
            'detail' => $summary->detail_level,
            'title' => $summary->title,
            'content' => $summary->content,
            'structured_content' => $summary->structured_content,
            'sections' => $summary->included_sections,
            'instructions' => $summary->instructions,
            'status' => $summary->status,
            'model' => $summary->model,
            'token_usage' => $summary->token_usage,
            'generated_by' => 'ai',
            'created_at' => optional($summary->created_at)->toISOString(),
            'updated_at' => optional($summary->updated_at)->toISOString(),
        ];
    }
}
