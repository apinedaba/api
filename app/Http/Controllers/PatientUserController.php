<?php

namespace App\Http\Controllers;

use App\Models\PatientUser;
use App\Models\Patient;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class PatientUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        return response()->json(data: PatientUser::with('patient')->where('user', $user->id)->get(), status: 200);
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

    public function enlacePacienteProfesional($patient)
    {
        $user = Auth::user();
        $checkExist = PatientUser::where('user', $user->id)->where('patient', $patient)->first();
        if (isset($checkExist->id)) {
            return [
                'rasson' => "Ya existe un paciente en tu lista con estos datos",
                'message' => "Usuario existente",
                'type' => "error"
            ];
        }

        $enlace = PatientUser::create([
            'user' => $user->id,
            'patient' => $patient
        ]);

        if ($enlace) {
            return $enlace;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(PatientUser $patientUser)
    {
        return $patientUser->first();
    }

    public function getCurrentProfesional()
    {
        $user = Auth::user();
        if (!$user->id) {
            return response()->json([
                'rasson' => "Tenemos problemas con tu usuario",
                'message' => "Problema de usuario no encontrado",
                'type' => "error"
            ]);
        }
        $currentRelation = PatientUser::where('patient', $user->id)->with('user')->get();
        return response()->json(data: $currentRelation, status: 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PatientUser $patientUser)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $enlace = PatientUser::where('user', $user->id)->where('patient', $request->id);
        $currentActive = $enlace->first()['activo'];
        if (isset($request->isPatient) && !$currentActive) {
            $enlace->update(["activo" => !$currentActive, 'status' => "Enlace Aceptado"]);
            $patient = Patient::where('id', $request->id)->first();
            EmailService::send(
                $patient->email,
                'Ahora tu cuenta de minsmeet esta completa.',
                'email.cuenta-activada-paciente',
                [
                    'name' => $patient->name,
                    'url' => config('app.frontend_url') . '/iniciar-sesion'
                ]
            );
            $response = [
                'message' => 'Paciente activado con exito',
                'status' => 'ok',
                'rasson' => "Haz aceptado con exito la peticion de tu psicologo, espera a que te de indicaciones de tu proxima cita, no es necesario que permanezcas en esta pagina",
                'type' => "success"
            ];
            return response()->json($response, 200);
        }
        if (isset($request->isPatient) && $currentActive) {
            $response = [
                'status' => 'ok',
                'message' => 'Paciente activado con exito',
                'rasson' => "Haz aceptado con exito la peticion de tu psicologo, espera a que te de indicaciones de tu proxima cita, no es necesario que permanezcas en esta pagina",
                'type' => "success"
            ];
            return response()->json($response, 200);
        }

        $currentActive = !$currentActive;
        $enlace->update(["activo" => $currentActive]);
        $currentActive = $currentActive === 1 ? "desactivado" : "activado";
        $response = [
            'rasson' => 'El usuario se a ' . $currentActive . ' correctamente',
            'message' => "Usuario $currentActive",
            'type' => "success"
        ];
        return response()->json($response, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PatientUser $patientUser)
    {
        //
    }
}
