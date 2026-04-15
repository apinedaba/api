<?php

namespace App\Http\Controllers;

use App\Events\LeadsEvent;
use App\Models\ConsultaContacto;
use App\Models\DeviceToken;
use App\Models\ProfessionalAnalyticsEvent;
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
            'lead_type' => 'nullable|in:session,package',
            'tipo_sesion' => 'nullable|string',
            'motivo' => 'nullable|string',
            'fecha' => 'required|string',
            'hora' => 'required|string',
            'user_id' => 'required|exists:users,id',
            'session_package_id' => 'nullable|exists:session_packages,id',
            'package_name' => 'nullable|string|max:255',
            'package_total_price' => 'nullable|numeric|min:0',
            'package_session_price' => 'nullable|numeric|min:0',
            'package_session_count' => 'nullable|integer|min:1',
            'precio' => 'nullable',
            'formato' => 'nullable|string',
            'categoria' => 'nullable',
            'discount' => 'nullable',
            'discount_type' => 'nullable',
            'codigo_descuento' => 'nullable|string',
            'lead_source' => 'nullable|string|max:80',
            'lead_medium' => 'nullable|string|max:80',
            'lead_campaign' => 'nullable|string|max:160',
            'landing_page' => 'nullable|string|max:160',
            'utm_source' => 'nullable|string|max:80',
            'utm_medium' => 'nullable|string|max:80',
            'utm_campaign' => 'nullable|string|max:160',
            'utm_content' => 'nullable|string|max:160',
            'utm_term' => 'nullable|string|max:160',
            'referrer' => 'nullable|string|max:255',
            'session_id' => 'nullable|string|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $payload = $validator->validated();
        $payload['lead_type'] = $payload['lead_type'] ?? 'session';
        $payload['tipo_sesion'] = $payload['tipo_sesion'] ?? 'Paquete de sesiones';

        if (empty($payload['motivo']) && $payload['lead_type'] === 'package') {
            $payload['motivo'] = 'Estoy interesado/a en contratar el paquete "' . ($payload['package_name'] ?? 'Paquete de sesiones') . '".';
        }

        $consulta = ConsultaContacto::create($payload);
        ProfessionalAnalyticsEvent::create([
            'user_id' => $consulta->user_id,
            'consulta_contacto_id' => $consulta->id,
            'event_type' => 'lead_submitted',
            'source' => $payload['lead_source'] ?? $payload['utm_source'] ?? null,
            'medium' => $payload['lead_medium'] ?? $payload['utm_medium'] ?? null,
            'campaign' => $payload['lead_campaign'] ?? $payload['utm_campaign'] ?? null,
            'landing_page' => $payload['landing_page'] ?? null,
            'referrer' => $payload['referrer'] ?? null,
            'session_id' => $payload['session_id'] ?? null,
            'ip_hash' => $request->ip()
                ? hash('sha256', $request->ip() . '|' . config('app.key'))
                : null,
            'metadata' => [
                'lead_type' => $consulta->lead_type,
                'session_package_id' => $consulta->session_package_id,
            ],
        ]);
        try {
            $consulta->notify(new ConfirmacionPaciente());
            $psicologo = \App\Models\User::find($request->user_id);
            if ($psicologo) {
                $psicologo->notify(new NuevoPosiblePaciente($consulta));
                event(new LeadsEvent($psicologo, $consulta));
                $tokens = DeviceToken::where('user_id', $psicologo->id)->pluck('token')->all();
                foreach ($tokens as $token) {
                    Fcm::send($token, "Nuevo contacto recibido", "Un visitante de mindmeet esta interesado en ti, su info esta disponible en leads", [
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
