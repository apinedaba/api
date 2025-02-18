<?php

namespace App\Http\Controllers;

use App\Models\Questionnaire;
use App\Models\QuestionnaireLink;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
class QuestionnaireLinkController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }



    public function generateLink(Request $request, $questionnaireId)
    {
        $questionnaire = Questionnaire::findOrFail($questionnaireId);

        $token = Str::uuid(); // Generar un token único
        $expiresAt = now()->addDays(7); // El enlace expira en 7 días

        $link = QuestionnaireLink::create([
            'questionnaire_id' => $questionnaire->id,
            'token' => $token,
            'expires_at' => $expiresAt,
            'user' => $request->user,
            'patient' => $request->patient
        ]);
        $response=[
            'rasson' => 'Questionario asignado',
            'message' => "Se asigno correctamente",
            'type' => "success",
            'token' => $token
            
        ];
        return response()->json($response);
    }

    public function showPublicQuestionnaire($token)
    {
        $link = QuestionnaireLink::where('token', $token)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $questionnaire = $link;

        if ($questionnaire->status === "pending") {
            # code...
            return response()->json($questionnaire->questionnaire);
        }else {
            return response()->json(["status"=>$questionnaire->status]);
        }

    }

    public function showQuestionnaireResponse($token, $user){
        $userAuth = Auth::user();
        if ((int)$user === $userAuth->id) {
            $link = QuestionnaireLink::where('token', $token)->where('user', (int)$user)
            ->with('questionnaireLink')->with('questionnaire')
            ->firstOrFail();
            return response()->json($link, 200);
        }

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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(QuestionnaireLink $questionnaireLink)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(QuestionnaireLink $questionnaireLink)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, QuestionnaireLink $questionnaireLink)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(QuestionnaireLink $questionnaireLink)
    {
        //
    }
}
