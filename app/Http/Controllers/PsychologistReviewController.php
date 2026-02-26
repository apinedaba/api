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
    public function index($psychologistId)
    {
        $reviews = PsychologistReview::where('psychologist_id', $psychologistId)
            ->where('approved', true)
            ->latest()
            ->get();

        $average = PsychologistReview::where('psychologist_id', $psychologistId)
            ->where('approved', true)
            ->avg('rating');

        return response()->json([
            'reviews' => $reviews,
            'average' => round($average, 1)
        ]);
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
    public function store(Request $request)
    {
        $request->validate([
            'psychologist_id' => 'required|exists:users,id',
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'device_id' => 'required|string'
        ]);

        $emailHash = hash('sha256', strtolower(trim($request->email)));

        $review = PsychologistReview::updateOrCreate(
            [
                'psychologist_id' => $request->psychologist_id,
                'email_hash' => $emailHash,
            ],
            [
                'name' => $request->name,
                'email' => $request->email,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'device_id' => $request->device_id,
            ]
        );

        return response()->json(['success' => true]);
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
