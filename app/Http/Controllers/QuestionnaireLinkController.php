<?php

namespace App\Http\Controllers;

use App\Models\Questionnaire;
use App\Models\QuestionnaireLink;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $data = $request->validate([
            // `questionnaire_links.patient` apunta al expediente clínico,
            // no a la cuenta del profesional en `users`.
            'patient' => ['nullable', 'integer', Rule::exists('patients', 'id')],
            'recipient_name' => ['required_without:patient', 'nullable', 'string', 'max:120'],
            'recipient_email' => ['required_without:patient', 'nullable', 'email:rfc', 'max:255'],
        ]);

        $questionnaire = Questionnaire::findOrFail($questionnaireId);

        // Un instrumento puede reutilizarse para seguimiento, pero no debe
        // haber dos enlaces activos del mismo cuestionario para la misma persona.
        $activeLink = QuestionnaireLink::query()
            ->where('questionnaire_id', $questionnaire->id)
            ->where('user', $request->user()->id)
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->when(
                ! empty($data['patient']),
                fn ($query) => $query->where('patient', $data['patient']),
                fn ($query) => $query->where('recipient_email', mb_strtolower($data['recipient_email']))
            )
            ->first();

        if ($activeLink) {
            return response()->json([
                'message' => 'Esta persona ya tiene un enlace activo para este cuestionario. Espera su respuesta o a que venza antes de asignarlo de nuevo.',
                'type' => 'duplicate_assignment',
                'token' => $activeLink->token,
            ], 422);
        }

        $token = Str::uuid(); // Generar un token único
        $expiresAt = now()->addDays(7); // El enlace expira en 7 días

        $link = QuestionnaireLink::create([
            'questionnaire_id' => $questionnaire->id,
            'token' => $token,
            'expires_at' => $expiresAt,
            'user' => $request->user()->id,
            'patient' => $data['patient'] ?? null,
            'recipient_name' => $data['recipient_name'] ?? null,
            'recipient_email' => isset($data['recipient_email'])
                ? mb_strtolower($data['recipient_email'])
                : null,
        ]);
        $response=[
            'rasson' => 'Questionario asignado',
            'message' => "Se asigno correctamente",
            'type' => "success",
            'token' => $token,
            'recipient' => $link->recipient_name,
            
        ];
        return response()->json($response);
    }

    public function showPublicQuestionnaire($token)
    {
        $link = QuestionnaireLink::where('token', $token)
            ->with('questionnaire')
            ->first();

        if (! $link) {
            return response()->json([
                'message' => 'No encontramos este enlace de cuestionario.',
                'type' => 'not_found',
            ], 404);
        }

        if ($link->expires_at && $link->expires_at->isPast()) {
            return response()->json([
                'message' => 'Este enlace de cuestionario ya expiro. Solicita a tu especialista que genere uno nuevo.',
                'type' => 'expired',
            ], 410);
        }

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
            ->with('questionnaireLink')->with('questionnaire')->with('patient')
            ->first();

            if (! $link) {
                return response()->json([
                    'message' => 'No encontramos la respuesta de este cuestionario para tu cuenta.',
                    'type' => 'not_found',
                ], 404);
            }

            return response()->json($link, 200);
        }

        return response()->json([
            'message' => 'No tienes permiso para ver esta respuesta.',
            'type' => 'forbidden',
        ], 403);

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
