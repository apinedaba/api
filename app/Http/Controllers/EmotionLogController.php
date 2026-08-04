<?php

namespace App\Http\Controllers;

use App\Models\EmotionLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmotionLogController extends Controller
{
    public function index(Request $request)
    {
        if ($request->has('patient_id')) {
            return EmotionLog::where('patient_id', $request->patient_id)
                            ->with('patient')
                            ->orderByDesc('date')
                            ->get();
        }

        $this->ensureGuardianUsesOwnProfile($request);
        $patient = $request->user(); 
        return EmotionLog::where('patient_id', $patient->id)
                        ->with('patient')
                        ->orderByDesc('date')
                        ->get();
    }



    public function store(Request $request)
    {
        $this->ensureGuardianUsesOwnProfile($request);
        $patient = $request->user(); // auth:patient

        $validated = $request->validate([
            'time' => 'required|date_format:H:i',
            'situation' => 'required|string',
            'emotion' => 'required|string|max:100',
            'intensity' => 'required|integer|min:0|max:10',
            'behavior' => 'required|string',
            'adaptive_response' => 'nullable|string',
            'feeling' => 'nullable|string|max:255', // New field validation
        ]);

        $validated['patient_id'] = $patient->id;
        $validated['date'] = now()->toDateString();

        $entry = EmotionLog::create($validated);
        return response()->json($entry, 201);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(EmotionLog $emotionLog)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EmotionLog $emotionLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EmotionLog $emotionLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmotionLog $emotionLog)
    {
        //
    }

    private function ensureGuardianUsesOwnProfile(Request $request): void
    {
        $patient = $request->user();
        $birthDate = data_get($patient, 'relevantes.fechaNac');
        abort_if(
            $birthDate && now()->diffInYears($birthDate) < 18,
            403,
            'El diario personal no está disponible para pacientes menores de edad.'
        );

        if (! $request->attributes->get('guardian_account')) return;

        $permissions = $request->attributes->get('guardian_patient_permissions');
        abort_unless(
            $permissions && $permissions->relationship === 'Titular',
            403,
            'El diario personal solo está disponible al seleccionar tu propio perfil.'
        );
    }
}
