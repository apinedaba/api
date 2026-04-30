<?php

namespace App\Http\Controllers;

use App\Events\LeadsEvent;
use App\Models\ConsultaContacto;
use App\Models\DeviceToken;
use App\Models\DiscountCoupon;
use App\Models\ProfessionalAnalyticsEvent;
use App\Services\Fcm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Notifications\NuevoContacto;
use App\Notifications\NuevoPosiblePaciente;
use App\Notifications\ConfirmacionPaciente;
use Illuminate\Validation\ValidationException;

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
            'duracion' => 'nullable|integer|min:1|max:8',
            'session_package_id' => 'nullable|exists:session_packages,id',
            'package_name' => 'nullable|string|max:255',
            'package_total_price' => 'nullable|numeric|min:0',
            'package_session_price' => 'nullable|numeric|min:0',
            'package_session_count' => 'nullable|integer|min:1',
            'precio' => 'nullable|numeric|min:0',
            'formato' => 'nullable|string',
            'categoria' => 'nullable',
            'discount' => 'nullable',
            'discount_type' => 'nullable',
            'codigo_descuento' => 'nullable|string',
            'coupon_code' => 'nullable|string',
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
        $couponCode = strtoupper(trim($payload['codigo_descuento'] ?? $payload['coupon_code'] ?? ''));
        unset($payload['codigo_descuento'], $payload['coupon_code']);

        $configurationErrors = $this->validateLeadConfiguration($payload);
        if ($configurationErrors) {
            throw ValidationException::withMessages($configurationErrors);
        }

        if (empty($payload['motivo']) && $payload['lead_type'] === 'package') {
            $payload['motivo'] = 'Estoy interesado/a en contratar el paquete "' . ($payload['package_name'] ?? 'Paquete de sesiones') . '".';
        }

        $payload = $this->applyCouponContext($payload, $couponCode);

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
                'coupon_code' => $consulta->coupon_code,
                'coupon_discount_amount' => $consulta->coupon_discount_amount,
                'final_amount' => $consulta->final_amount,
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

    protected function applyCouponContext(array $payload, string $couponCode): array
    {
        $subtotal = $this->resolveSubtotal($payload);
        $payload['subtotal_amount'] = $subtotal > 0 ? $subtotal : null;
        $payload['coupon_discount_amount'] = null;
        $payload['final_amount'] = $subtotal > 0 ? $subtotal : null;

        if ($couponCode === '') {
            return $payload;
        }

        $coupon = DiscountCoupon::query()
            ->where('user_id', $payload['user_id'])
            ->where('code', $couponCode)
            ->first();

        if (!$coupon || !$coupon->is_currently_available || !$coupon->appliesToLeadType($payload['lead_type'])) {
            throw ValidationException::withMessages([
                'codigo_descuento' => ['El cupon no esta disponible para esta solicitud.'],
            ]);
        }

        $discountAmount = $coupon->calculateDiscountForAmount($subtotal);

        $payload['discount_coupon_id'] = $coupon->id;
        $payload['coupon_code'] = $coupon->code;
        $payload['coupon_discount_type'] = $coupon->discount_type;
        $payload['coupon_discount_value'] = (float) $coupon->discount_value;
        $payload['coupon_discount_amount'] = $discountAmount;
        $payload['final_amount'] = round(max($subtotal - $discountAmount, 0), 2);

        return $payload;
    }

    protected function resolveSubtotal(array $payload): float
    {
        if (($payload['lead_type'] ?? 'session') === 'package') {
            return (float) ($payload['package_total_price'] ?? 0);
        }

        return (float) ($payload['precio'] ?? 0);
    }

    protected function validateLeadConfiguration(array $payload): array
    {
        if (($payload['lead_type'] ?? 'session') === 'package') {
            $missing = [];

            if (empty($payload['session_package_id']) && empty($payload['package_name'])) {
                $missing['session_package_id'] = ['Selecciona el paquete que te interesa antes de enviar la solicitud.'];
            }

            if ((float) ($payload['package_total_price'] ?? 0) <= 0) {
                $missing['package_total_price'] = ['El paquete debe incluir un precio valido antes de crear el lead.'];
            }

            return $missing;
        }

        $missing = [];

        if (blank($payload['tipo_sesion'] ?? null)) {
            $missing['tipo_sesion'] = ['Selecciona el tipo de sesion.'];
        }

        if (blank($payload['formato'] ?? null)) {
            $missing['formato'] = ['Selecciona la modalidad de la sesion.'];
        }

        if ((int) ($payload['duracion'] ?? 0) <= 0) {
            $missing['duracion'] = ['Selecciona una duracion valida para la sesion.'];
        }

        if ((float) ($payload['precio'] ?? 0) <= 0) {
            $missing['precio'] = ['Selecciona una configuracion con precio antes de enviar la solicitud.'];
        }

        return $missing;
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
