<?php

namespace App\Http\Controllers;

use App\Models\PsychologistReview;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PsychologistReviewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($id)
    {
        return PsychologistReview::with('patient')
            ->where('psychologist_id', $id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $id)
    {

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:1000',
        ]);

        
        $patient = $request->user();
        
        $hasAttended = \App\Models\Appointment::where('patient', $patient->id)
            ->where('user', $id)
            ->whereIn('statusUser', ['confirm', 'finish', "success", 'Pending Approve']) // o el status que uses
            ->exists();

        if (!$hasAttended) {
            return response()->json([
                'message' => 'Solo puedes dejar una opinión si has tenido al menos una cita con este profesional.',
                'patient' =>$patient->first(),
                'user'=>$id
            ], 403);
        }

        $review = PsychologistReview::updateOrCreate(
            [
                'patient_id' => $patient->id,
                'psychologist_id' => $id,
            ],
            [
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]
        );

        return response()->json($review, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(PsychologistReview $psychologistReview)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PsychologistReview $psychologistReview)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PsychologistReview $psychologistReview)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PsychologistReview $psychologistReview)
    {
        //
    }
}
