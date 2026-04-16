<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $test = Auth::user();
        // Log::alert($test->currentAccessToken()->type);
        $users = User::with('appointment')->get();
        return response()->json($users, 200);
    }

    function solicitudDeVerificacion($id)
    {
        $user = User::where('id', $id)->first();
        \Log::info('Solicitud de verificacion para: ' . $user->name);
        $email = EmailService::send(
            $user->email,
            'Notificación del equipo MindMeet',
            'email.notify-update-profile',
            [
                'name' => $user->name,
                'missingFields' => ['Foto cédula profesional', 'Foto INE'],
                'url' => config('app.frontend_url') . '/perfil'
            ]
        );
        return response()->json($email, 200);
    }

    function getAllUsers()
    {
        $users = User::with('subscription')->orderBy('identity_verification_status', 'desc')->get();
        return Inertia::render('Psicologos', [
            'psicologos' => $users,
            'status' => session('status'),
        ]);
    }

    public function getProfessional()
    {
        $allUser = User::where('isProfileComplete', true)
            ->where('activo', true)
            ->where('stripe_id', '!=', null)
            ->orWhere('has_lifetime_access', true)
            ->get();
        return response()->json($allUser, 200);
    }

    public function getProfessionalById($id)
    {
        $allUser = User::query()
            ->publiclyVisible()
            ->where('id', $id)
            ->with(['escuelas', 'activeSessionPackages', 'activeDiscountCoupons'])
            ->firstOrFail();

        return response()->json($allUser, 200);
    }

    public function getProfessionalTagsById($id)
    {
        $allUser = User::where('id', $id)->first();
        return response()->json($allUser, 200);
    }

    public function desactive($id)
    {
        $user = User::where('id', $id)->first();
        $user->update([
            'activo' => false
        ]);
        return Inertia::render('Psicologos/Edit', [
            'psicologo' => $user
        ]);
    }

    public function active($id)
    {
        $user = User::where('id', $id)->first();
        $user->update([
            'activo' => true
        ]);
        return Inertia::render('Psicologos/Edit', [
            'psicologo' => $user
        ]);
    }

    public function getAvailableSlots(Request $request)
    {
        $userId = $request->id;

        // Obtener la fecha de hoy
        $today = Carbon::today();
        // Obtener la fecha de 10 días a partir de hoy
        $endDate = $today->copy()->addDays(10);

        // Obtener citas del médico para los próximos 10 días

        $appointments = Appointment::whereHas('patient_user', function ($query) use ($userId) {
            $query->where('user', $userId);
        })->whereBetween('fecha', [$today, $endDate])->get();

        // Aquí defines los horarios en los que el médico trabaja, por ejemplo, de 9am a 5pm
        $workingHours = [
            '09:00:00',
            '10:00:00',
            '11:00:00',
            '12:00:00',
            '13:00:00',
            '14:00:00',
            '15:00:00',
            '16:00:00',
            '17:00:00'
        ];

        // Crear un array con los días y horarios disponibles
        $availableSlots = [];

        // Iterar sobre los próximos 10 días
        for ($date = $today; $date->lte($endDate); $date->addDay()) {
            $dateString = $date->format('Y-m-d');

            // Obtener las citas ya reservadas para ese día
            $bookedSlots = $appointments->where('fecha', $dateString)->pluck('hora')->toArray();

            // Comparar horarios de trabajo con las citas reservadas para encontrar disponibles
            $availableTimes = array_diff($workingHours, $bookedSlots);
            // Añadir los horarios disponibles para este día
            if (!empty($availableTimes)) {
                $availableSlots[$dateString] = $availableTimes;
            }
        }

        return response()->json($availableSlots);
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

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $user = User::findOrFail($id)->load([
                'escuelas',
                'subscription',
                'sessionPackages',
                'discountCoupons',
                'googleAccount',
            ]);
            if ($user) {
                return Inertia::render('Psicologos/Edit', [
                    'psicologo' => $user,
                    'publicVisibility' => $this->publicVisibilitySummary($user),
                ]);
            }
        } catch (\Throwable $th) {
            return Inertia::render('Psicologos/Edit', [
                'error' => 'No se encontro el usuario'
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $user->update($request->all());
        return Inertia::render('Psicologos/Edit', [
            'psicologo' => $user
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);
        $user = $request->where('id', $id);
        $user->update(
            [
                'activo' => false
            ]
        );
        return response()->json(['ok' => true], 200);
    }

    public function ensurePublicVisibility(Request $request, string $id)
    {
        $request->validate([
            'grant_lifetime_access' => ['nullable', 'boolean'],
        ]);

        $user = User::with('subscription')->findOrFail($id);
        $hasBillableAccess = $user->has_lifetime_access
            || in_array(optional($user->subscription)->stripe_status, ['active', 'trialing'], true);

        if (!$hasBillableAccess && !$request->boolean('grant_lifetime_access')) {
            throw ValidationException::withMessages([
                'grant_lifetime_access' => 'Este psicologo no tiene suscripcion activa/prueba activa. Autoriza acceso permanente para hacerlo visible sin Stripe.',
            ]);
        }

        $user->forceFill([
            'activo' => true,
            'isProfileComplete' => true,
            'identity_verification_status' => 'approved',
            'email_verified_at' => $user->email_verified_at ?: now(),
            'has_lifetime_access' => $user->has_lifetime_access || $request->boolean('grant_lifetime_access'),
        ])->save();

        return redirect()
            ->route('psicologoShow', $user->id)
            ->with('status', 'Psicologo listo para visibilidad publica.');
    }

    private function publicVisibilitySummary(User $user): array
    {
        $subscriptionStatus = optional($user->subscription)->stripe_status;
        $hasBillableAccess = $user->has_lifetime_access
            || in_array($subscriptionStatus, ['active', 'trialing'], true);

        $checks = [
            [
                'key' => 'activo',
                'label' => 'Cuenta activa',
                'ok' => (bool) $user->activo,
                'detail' => $user->activo ? 'Activo' : 'Inactivo',
            ],
            [
                'key' => 'isProfileComplete',
                'label' => 'Perfil completo',
                'ok' => (bool) $user->isProfileComplete,
                'detail' => $user->isProfileComplete ? 'Completo' : 'Incompleto',
            ],
            [
                'key' => 'identity',
                'label' => 'Identidad aprobada',
                'ok' => $user->identity_verification_status === 'approved',
                'detail' => $user->identity_verification_status ?: 'Sin estado',
            ],
            [
                'key' => 'email_verified',
                'label' => 'Correo verificado',
                'ok' => !is_null($user->email_verified_at),
                'detail' => $user->email_verified_at ? $user->email_verified_at->format('Y-m-d H:i') : 'Sin verificar',
            ],
            [
                'key' => 'billable_access',
                'label' => 'Suscripcion o acceso permanente',
                'ok' => $hasBillableAccess,
                'detail' => $user->has_lifetime_access ? 'Acceso permanente' : ($subscriptionStatus ?: 'Sin suscripcion'),
            ],
        ];

        return [
            'visible' => collect($checks)->every(fn($check) => $check['ok']),
            'checks' => $checks,
            'subscription_status' => $subscriptionStatus,
            'has_billable_access' => $hasBillableAccess,
            'catalog_url' => url('/share/profesional/' . $user->id),
        ];
    }

    /**
     * Validate identity documents (cedula and INE)
     */
    public function validateIdentity(Request $request, string $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'type' => 'nullable|in:cedula,ine,both'
        ]);

        $user = User::findOrFail($id);
        $type = $request->type ?? 'both';

        if ($request->action === 'approve') {
            $user->identity_verification_status = 'approved';
            $message = 'Identidad verificada correctamente';

            // Enviar email de aprobación
            EmailService::send(
                $user->email,
                'Identidad Verificada - MindMeet',
                'email.identity-approved',
                [
                    'name' => $user->name
                ]
            );
        } else {
            $user->identity_verification_status = 'rejected';

            // Si se rechaza, eliminar la URL de la imagen correspondiente
            $documentType = '';
            if ($type === 'cedula' || $type === 'both') {
                $user->cedula_selfie_url = null;
                $documentType = $type === 'both' ? 'tu cédula profesional e INE' : 'tu cédula profesional';
            }

            if ($type === 'ine' || $type === 'both') {
                $user->ine_selfie_url = null;
                $documentType = $type === 'cedula' ? 'tu cédula profesional e INE' : ($type === 'ine' ? 'tu INE' : 'tu cédula profesional e INE');
            }

            $message = 'Identidad rechazada. El usuario deberá subir nuevamente las imágenes.';

            // Enviar email de rechazo
            EmailService::send(
                $user->email,
                'Verificación de Identidad - Acción Requerida - MindMeet',
                'email.identity-rejected',
                [
                    'name' => $user->name,
                    'documentType' => $documentType,
                    'url' => config('app.frontend_url') . '/perfil'
                ]
            );
        }

        $user->save();

        return redirect()->route('psicologoShow', $user->id)->with('status', $message);
    }
}
