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
            'structure' => 'required|array',
        ], $this->messages);

        if ($validate->fails()) {
            return response()->json([
                'message' => 'Ha ocurrido un error de validación',
                'errors' => $validate->errors(),
                'type' => "error"
            ], 400);
        }

        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'message' => 'Usuario no autenticado',
                    'type' => 'error'
                ], 401);
            }

            $questionnaire = Questionnaire::create([
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'structure' => $request->input('structure'),
                'user' => $user->id
            ]);

            return response()->json(
                [
                    'rasson' => "El cuestionario se creó con éxito",
                    'message' => "Cuestionario agregado",
                    'type' => "success",
                    "data" => $questionnaire
                ],
                201
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el cuestionario',
                'error' => $e->getMessage(),
                'type' => 'error'
            ], 500);
        }
    }
    // En el controlador QuestionnaireController
    public function submitResponses(Request $request, $questionnaireToken)
    {
        $request->validate([
            'response' => 'required|array',
        ]);
        try {
            $qt = QuestionnaireLink::where('token', $questionnaireToken);
            $response = $qt->firstOrFail();
            $checkResponse = QuestionnairesLinkResponses::where('questionnaire_link_id', $response->id)->count();
            if ($checkResponse > 0) {
                $update = $qt->update(["status" => "completed"]);
                return response()->json([
                    'message' => 'Respuestas guardadas exitosamente.',
                    "response" => $update,
                    "status" => "completed",
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
                    "status" => "completed",
                ]);
                if ($response) {
                    $update = $qt->update(["status" => "completed"]);
                }
                return response()->json([
                    'message' => 'Respuestas guardadas exitosamente.',
                    "response" => $update,
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
    public function getQuestionnairesByPatient($patient)
    {
        return QuestionnaireLink::where('patient', $patient)->with('questionnaire')->get();
    }

    /**
     * Obtener los cuestionarios asignados al paciente autenticado.
     */
    public function getQuestionnairesForPatient(Request $request)
    {
        $user = Auth::user();
        // Devuelve los enlaces (links) de cuestionarios asignados al paciente autenticado
        return QuestionnaireLink::where('patient', $user->id)
            ->with('questionnaire', 'patient')
            ->get();
    }
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $questionnaire = Questionnaire::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $questionnaire
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cuestionario no encontrado',
                'error' => 'El cuestionario solicitado no existe'
            ], 404);
        }
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
    public function update(Request $request, $id)
    {
        try {
            $questionnaire = Questionnaire::findOrFail($id);

            // Validar que el usuario es el propietario
            if ($questionnaire->user !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado',
                    'error' => 'No tienes permiso para editar este cuestionario'
                ], 403);
            }

            $response = $questionnaire->update($request->only('title', 'description', 'structure'));
            return response()->json(
                [
                    'rasson' => "El cuestionario se actualizó con éxito",
                    'message' => "Cuestionario actualizado",
                    'type' => "success",
                    "data" => $questionnaire
                ],
                200
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cuestionario no encontrado',
                'error' => 'El cuestionario solicitado no existe'
            ], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $questionnaire = Questionnaire::findOrFail($id);

            // Validar que el usuario es el propietario
            if ($questionnaire->user !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado',
                    'error' => 'No tienes permiso para eliminar este cuestionario'
                ], 403);
            }

            $questionnaire->delete();
            return response()->json([
                'success' => true,
                'message' => 'Cuestionario eliminado',
                'type' => 'success'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cuestionario no encontrado',
                'error' => 'El cuestionario solicitado no existe'
            ], 404);
        }
    }
}
