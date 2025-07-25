<?php

namespace App\Http\Controllers;

use App\Models\EmotionLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmotionLogController extends Controller
{
    public function index(Request $request)
    {
        $patient = $request->user(); // auth:patient
        return EmotionLog::where('patient_id', $patient->id)->with('patient')->orderByDesc('date')->get();
    }

    public function store(Request $request)
    {
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
}
