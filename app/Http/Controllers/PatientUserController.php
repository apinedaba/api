<?php

namespace App\Http\Controllers;

use App\Models\PatientUser;
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
        return response()->json( data: PatientUser::with('patient')->where('user', $user->id)->get(), status: 200);
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

    public function enlacePacienteProfesional($patient){
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
        //
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
    public function update(Request $request, PatientUser $patientUser)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PatientUser $patientUser)
    {
        //
    }
}
