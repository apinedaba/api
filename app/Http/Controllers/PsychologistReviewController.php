<?php

namespace App\Http\Controllers;

use App\Models\PsychologistReview;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


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
    private $validations = [
        'psychologist_id' => 'required|exists:users,id',
        'name' => 'required|string|max:100',
        'email' => 'required|email|max:255|unique:psychologist_reviews,email',
        'rating' => 'required|integer|min:1|max:5',
        'comment' => 'nullable|string|max:2000',
        'device_id' => 'required|string|unique:psychologist_reviews,device_id'
    ];

    private $customMessages = [
        'psychologist_id.required' => 'El psicólogo es requerido.',
        'psychologist_id.exists' => 'El psicólogo no existe.',
        'name.required' => 'El nombre es requerido.',
        'name.max' => 'El nombre debe tener máximo 100 caracteres.',
        'email.required' => 'El correo es requerido.',
        'email.email' => 'El correo debe ser un correo válido.',
        'email.unique' => 'Ya haz dejado una reseña con este correo.',
        'rating.required' => 'La calificación es requerida.',
        'rating.integer' => 'La calificación debe ser un número entero.',
        'rating.min' => 'La calificación debe ser mínimo 1.',
        'rating.max' => 'La calificación debe ser máximo 5.',
        'comment.max' => 'El comentario debe tener máximo 2000 caracteres.',
        'device_id.unique' => 'Ya haz dejado una reseña con este dispositivo.'
    ];
    public function store(Request $request)
    {
        $validations = Validator::make($request->all(), $this->validations, $this->customMessages);
        if ($validations->fails()) {
            return response()->json(['success' => false, 'message' => $validations->errors()], 422);
        }

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
