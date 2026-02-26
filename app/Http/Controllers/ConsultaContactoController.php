<?php

namespace App\Http\Controllers;

use App\Models\ConsultaContacto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Notifications\NuevoContacto;

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

        // 1. Creamos el registro en la base de datos
        $consulta = ConsultaContacto::create($request->all());

        // 2. Intentamos enviar la notificación por correo
        try {
            // Usamos la clase NuevoContacto que ya importaste
            // Pasamos $consulta como el "paciente" que espera tu constructor
            $consulta->notify(new NuevoContacto($consulta));
        } catch (\Throwable $th) {
            // Si el correo falla, lo registramos en el log pero dejamos que el usuario vea éxito
            \Log::error("Error enviando notificación de contacto: " . $th->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => '¡Consulta enviada con éxito!',
            'data' => $consulta
        ], 201);
    }
}