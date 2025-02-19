<?php

namespace App\Http\Controllers;

use App\Models\Questionnaire;
use App\Models\QuestionnaireLink;
use App\Models\QuestionnairesLinkResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class QuestionnaireController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $questionnaries = Questionnaire::where('user', $user->id)->get();
        return $questionnaries;
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
    protected $messages = [
        'title.required' => 'El Titulo del formulario es requerido',
    ];

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'title' => 'required|string|max:255|min:10',
            'description' => 'nullable|string',
            'structure' => 'required|json',
        ], $this->messages);
        if ($validate->fails()) {
            return response()->json([
                'message' => 'Ha ocurrido un error de validación',
                'errors' => $validate->errors(),
                'type' => "error"
            ], 400);
        }

        $questionnaire = Questionnaire::create($request->only('title', 'description', 'structure', 'user'));
        return response()->json(
            [
                'rasson' => "El cuestionario se creo con exito",
                'message' => "Cuestionario agregado",
                'type' => "success",
                "data" => $questionnaire
            ]
            ,
            200
        );
    }
    // En el controlador QuestionnaireController
    public function submitResponses(Request $request, $questionnaireToken)
    {
        $request->validate([
            'response' => 'required|json',
        ]);
        try {
            $qt = QuestionnaireLink::where('token', $questionnaireToken);
            $response = $qt->firstOrFail();
            $checkResponse = QuestionnairesLinkResponses::where('questionnaire_link_id', $response->id)->count();
            if ($checkResponse > 0) {
                $update = $qt->update(["status"=>"completed"]);
                return response()->json([
                    'message' => 'Respuestas guardadas exitosamente.',
                    "response"=> $update,
                    "status"=>"completed",                    
                    'alert' => [
                        'rasson' => 'El cuestionario no puede ser respondido de nuevo, el estado del cuestionario se actualizo',
                        'message' => "No es posible llenar de nuevo ",
                        'type' => "error"
                    ]
                ]);

            }
            if ($response && $response->status == "pending") {                                
                $response = QuestionnairesLinkResponses::create([
                    'questionnaire_link_id' => $response->id,
                    'response' => $request->response,
                    "status"=>"completed",                    
                ]);
                if ($response) {
                    $update = $qt->update(["status"=>"completed"]);
                }
                return response()->json([
                    'message' => 'Respuestas guardadas exitosamente.',
                    "response"=> $update,
                    'alert' => [
                        'rasson' => 'El cuestionario se envio con exito, ya notificamos a tu profesional',
                        'message' => "Cuestionario enviado ",
                        'type' => "success"
                    ]
                ]);

            }    

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json(['error' => $th->getMessage()]);


        }

    }
    public function getQuestionnairesByPatient($patient)  {
        return QuestionnaireLink::where('patient', $patient)->with('questionnaire')->get();
    }
    /**
     * Display the specified resource.
     */
    public function show(Questionnaire $questionnaire)
    {
        return response()->json($questionnaire);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Questionnaire $questionnaire)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Questionnaire $questionnaire)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Questionnaire $questionnaire)
    {
        //
    }
}
