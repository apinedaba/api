<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExpedienteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $expedientes = Expediente::where('user_id', auth()->id())->first();
        return response()->json($expedientes);
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
        $patient_id = $request->patient_id;
        $isUpdatde = Expediente::where('user_id', auth()->id())->where('patient_id', $patient_id)->exists();
        if ($isUpdatde) {
            $expediente = Expediente::where('user_id', auth()->id())->where('patient_id', $patient_id)->first();

            $data = [
                'user_id' => auth()->id(),
                'patient_id' => $patient_id,
            ];

            if ($request->examen_mental) {
                $data['examen_mental'] = $request->examen_mental;
            }
            if ($request->antecedentes) {
                $data['antecedentes'] = $request->antecedentes;
            }
            if ($request->motivoConsulta) {
                $data['motivoConsulta'] = $request->motivoConsulta;
            }
            if ($request->plan_tratamiento) {
                $data['plan_tratamiento'] = $request->plan_tratamiento;
            }
            if ($request->dinamicaFamiliar) {
                $data['dinamicaFamiliar'] = $request->dinamicaFamiliar;
            }
            if ($request->vidaSocial) {
                $data['vidaSocial'] = $request->vidaSocial;
            }
            if ($request->escalas) {
                $data['escalas'] = $request->escalas;
            }
            if ($request->linea_vida) {
                $data['linea_vida'] = $request->linea_vida;
            }
            if ($request->diagnostico) {
                $data['diagnostico'] = $request->diagnostico;
            }
            if ($request->firma) {
                $data['firma'] = $request->firma;
            }

            $expediente->update($data);
        } else {
            $expediente = Expediente::create([
                'user_id' => auth()->id(),
                'patient_id' => $patient_id,
                'escalas' => $request->escalas ?? [],
                'linea_vida' => $request->linea_vida ?? [],
                'diagnostico' => $request->diagnostico ?? "",
                'motivoConsulta' => $request->motivoConsulta ?? "",
                'firma' => $request->firma ?? "",
            ]);
        }
        $expediente['isUpdate'] = $isUpdatde;
        return response()->json($expediente, 200);
    }

    /**
     * Display the specified resource.
     */
    public function show($patient_id)
    {
        $expediente = Expediente::where('user_id', auth()->id())->where('patient_id', $patient_id)->first();
        return response()->json($expediente);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Expediente $expediente)
    {
        $expediente = Expediente::where('user_id', auth()->id())->find($expediente->id);
        return response()->json($expediente);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expediente $expediente)
    {
        $expediente = Expediente::where('user_id', auth()->id())->find($expediente->id);
        $expediente->update([
            'user_id' => auth()->id(),
            'paciente_id' => $request->paciente_id,
            'escalas' => $request->escalas,
            'linea_vida' => $request->linea_vida,
            'diagnostico' => $request->diagnostico,
            'firma' => $request->firma,
        ]);
        return response()->json($expediente);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expediente $expediente)
    {
        //
    }
}
