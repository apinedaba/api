<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\PatientUserController;
use Illuminate\Support\Facades\Auth;
use App\Notifications\PatientAssignedEmailNotification;
use Illuminate\Support\Facades\Log;

class PatientController extends Controller
{

    protected $_patient;

    public function __construct() {
        $this->_patient = new PatientUserController;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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

    private $registerValidationRules = [
        'name' => 'required',
        'email' => 'required|email|unique:patients,email',
        'password' => 'required'
    ];

    public function store(Request $request)
    {
        $patient = new Patient();
        $data = $request->all();
        $telefono = $data['contacto']['telefono'];
        if (!isset($data["password"])) {
            $data["password"]= Hash::make($telefono);
        }    
        
        $validateUser = Validator::make($data, $this->registerValidationRules);
        
        if($validateUser->fails()){
            $patient = $patient->where('email', $request->email)->firstOrFail();
        }else {
            $data['contacto'] = json_encode($data['contacto']);
            $patient->fill($data);
            $patient->save();   
        }
        $enlace = $this->_patient->enlacePacienteProfesional($patient->id);  
        if (isset($enlace['message'])) {
            return response()->json($enlace, 400);
        }
        $user = Auth::user();
        if ($enlace) {
            $send = $this->sendNotificacionEmailByUser($user, $patient, $enlace);
            if ($send) {
                return response()->json(  [
                    'rasson' => "El paciente se agrego con exito espera a que acepte la invitacion para poder agendarle citas",
                    'message' => "Paciente agregado",
                    'type' => "success",
                    "data" => $enlace
                    ]
                , 200);
            }else{
                return response()->json(  [
                    'rasson' => "El paciente se agrego con exito pero no fue posible entregarle el correo, revisa sus datos e intent enviar de nuevo el correo",
                    'message' => "Paciente agregado",
                    'type' => "success",
                    "data" => $enlace
                    ]
                , 200);
            }
            
        }
    }
    
    public function sendNotificacionEmailByUser($user, $patient, $enlace){
        if ($enlace) {            
            try {
                //code...
                $patient->notify(new PatientAssignedEmailNotification($user, $patient, $enlace));
                return true;
            } catch (\Throwable $th) {
                Log::error($th->getMessage());
                //throw $th;
            }
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Patient $patient)
    {   

        return response()->json($patient, 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Patient $patient)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Patient $patient)
    {
        try {
            $patient->update($request->all());
            $response = [
                'rasson' => 'El usuario se a actualizado correctamente',
                'message' => "Usuario actulizado ",
                'type' => "success"
            ];
            
        } catch (\Throwable $th) {
            $response = [
                'rasson' => 'El usuario no se a actualizado correctamente',
                'message' => "Usuario no actulizado",
                'type' => "error"
            ];
        }

        return response()->json($response, 200);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Patient $patient)
    {
    }
}
