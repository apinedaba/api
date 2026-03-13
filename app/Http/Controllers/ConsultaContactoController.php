<?php

namespace App\Http\Controllers;

use App\Events\LeadsEvent;
use App\Models\ConsultaContacto;
use App\Models\DeviceToken;
use App\Services\Fcm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Notifications\NuevoContacto;
use App\Notifications\NuevoPosiblePaciente;
use App\Notifications\ConfirmacionPaciente;

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
            if ($psicologo) {
                $psicologo->notify(new NuevoPosiblePaciente($consulta));
                event(new LeadsEvent($psicologo, $consulta));
                $tokens = DeviceToken::where('user_id', $psicologo->id)->pluck('token')->all();
                foreach ($tokens as $token) {
                    Fcm::send($token, "Tienes un nuevo posible paciente", "Tienes un nuevo posible paciente", [
                        'link' => 'https://minder.mindmeet.com.mx/leads',
                        'icon' => 'https://res.cloudinary.com/dabwvv94x/image/upload/v1764639595/android-chrome-192x192_aogrgh.png'
                    ]);
                }
            }
        } catch (\Throwable $th) {
            \Log::error("ERROR REAL: " . $th->getMessage());
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
