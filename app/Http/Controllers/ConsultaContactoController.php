<?php

namespace App\Http\Controllers;

use App\Models\ConsultaContacto;
use App\Notifications\NotificacionPsicologo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Notifications\NuevoContacto;
use App\Notifications\NuevoPosibleContacto;

class ConsultaContactoController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telefono' => 'required|string|max:20',
            'tipo_sesion' => 'required|string',
            'motivo' => 'required|string',
            'fecha' => 'required|string',
            'hora' => 'required|string',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $consulta = ConsultaContacto::create($request->all());
        try {
            $consulta->notify(new ConfirmacionPaciente());
            $psicologo = \App\Models\User::find($request->user_id);
            \Log::info("PSICOLGO: " . $psicologo);
            if ($psicologo) {
                $psicologo->notify(new NotificacionPsicologo($consulta, $psicologo));
            }
        } catch (\Throwable $th) {
            \Log::error("Error enviando notificación de contacto: " . $th->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => '¡Consulta enviada con éxito!',
            'data' => $consulta
        ], 201);
    }
    public function getData()
    {
        $userId = auth()->id();
        $consultas = \App\Models\ConsultaContacto::where('user_id', $userId)
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $consultas
        ], 200);
    }
}
