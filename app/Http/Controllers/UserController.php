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

    function getAllUsers(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $allowedFilters = ['all', 'public_visible', 'active', 'identity_review', 'rejected', 'incomplete_profiles', 'without_subscription'];
        $filter = in_array($filter, $allowedFilters, true) ? $filter : 'all';

        $baseQuery = User::query();

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'public_visible' => User::query()->publiclyVisible()->count(),
            'active' => (clone $baseQuery)->where('identity_verification_status', 'approved')->count(),
            'identity_review' => (clone $baseQuery)
                ->whereIn('identity_verification_status', ['pending', 'sending'])
                ->whereNotNull('cedula_selfie_url')
                ->whereNotNull('ine_selfie_url')
                ->count(),
            'rejected' => (clone $baseQuery)->where('identity_verification_status', 'rejected')->count(),
            'incomplete_profiles' => (clone $baseQuery)
                ->where(fn ($query) => $query->where('isProfileComplete', false)->orWhereNull('isProfileComplete'))
                ->count(),
            'without_subscription' => (clone $baseQuery)
                ->where('has_lifetime_access', false)
                ->whereDoesntHave('subscription')
                ->count(),
        ];

        $users = User::with('subscription')
            ->when($filter === 'public_visible', fn ($query) => $query->publiclyVisible())
            ->when($filter === 'active', fn ($query) => $query->where('identity_verification_status', 'approved'))
            ->when($filter === 'identity_review', function ($query) {
                $query->whereIn('identity_verification_status', ['pending', 'sending'])
                    ->whereNotNull('cedula_selfie_url')
                    ->whereNotNull('ine_selfie_url');
            })
            ->when($filter === 'rejected', fn ($query) => $query->where('identity_verification_status', 'rejected'))
            ->when($filter === 'incomplete_profiles', function ($query) {
                $query->where(fn ($subquery) => $subquery->where('isProfileComplete', false)->orWhereNull('isProfileComplete'));
            })
            ->when($filter === 'without_subscription', function ($query) {
                $query->where('has_lifetime_access', false)
                    ->whereDoesntHave('subscription');
            })
            ->orderBy('identity_verification_status', 'desc')
            ->get();

        return Inertia::render('Psicologos', [
            'psicologos' => $users,
            'summary' => $summary,
            'filters' => [
                'filter' => $filter,
                'focus' => $request->query('focus'),
            ],
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
        if (! $user->syncOperationalStatus()) {
            throw ValidationException::withMessages([
                'activo' => 'El psicologo necesita telefono, perfil, especialidades, horarios y al menos un servicio antes de activar su cuenta.',
            ]);
        }
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
        $data = $request->all();

        if ($request->has('contacto')) {
            $contact = array_merge($user->contacto ?? [], $request->input('contacto', []));
            $phone = User::normalizePhone(data_get($contact, 'telefono'));

            if (preg_match('/^\d{10}$/', $phone) !== 1) {
                throw ValidationException::withMessages([
                    'contacto.telefono' => 'El telefono es obligatorio y debe contener exactamente 10 digitos.',
                ]);
            }

            $data['contacto'] = array_merge($contact, ['telefono' => $phone]);
        }

        $user->update($data);
        $user->refresh()->syncOperationalStatus();
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
        $user = User::with('subscription')->findOrFail($id);
        $hasBillableAccess = $user->hasBillableAccess();

        if (!$hasBillableAccess) {
            throw ValidationException::withMessages([
                'membership_type' => 'Este psicologo no tiene una membresia activa. Asigna una membresia antes de habilitar su visibilidad.',
            ]);
        }

        if ($user->identity_verification_status !== 'approved') {
            throw ValidationException::withMessages([
                'identity_verification_status' => 'La identidad debe estar aprobada antes de habilitar la visibilidad.',
            ]);
        }

        if (! $user->email_verified_at) {
            throw ValidationException::withMessages([
                'email_verified_at' => 'El correo debe estar verificado antes de habilitar la visibilidad.',
            ]);
        }

        $user->syncPhoneFromWhatsapp();

        if (! $user->hasOperationalSetup()) {
            throw ValidationException::withMessages([
                'activo' => 'Completa el telefono, perfil, especialidades, horarios y al menos un servicio antes de habilitar la visibilidad.',
            ]);
        }

        $user->forceFill([
            'activo' => true,
        ])->save();

        return redirect()
            ->route('psicologoShow', $user->id)
            ->with('status', 'Psicologo listo para visibilidad publica.');
    }

    public function updateMembership(Request $request, string $id)
    {
        $validated = $request->validate([
            'membership_type' => ['required', 'in:none,lifetime,content_creator'],
        ]);

        $user = User::findOrFail($id);
        $membershipType = $validated['membership_type'] === 'none'
            ? null
            : $validated['membership_type'];

        $user->forceFill([
            'membership_type' => $membershipType,
            'has_lifetime_access' => $membershipType !== null,
        ])->save();

        $label = match ($membershipType) {
            'content_creator' => 'Creador de contenido',
            'lifetime' => 'Acceso permanente',
            default => 'Sin membresia especial',
        };

        return redirect()
            ->route('psicologoShow', $user->id)
            ->with('status', "Membresia actualizada: {$label}.");
    }

    private function publicVisibilitySummary(User $user): array
    {
        $subscriptionStatus = optional($user->subscription)->stripe_status;
        $subscriptionStripeId = optional($user->subscription)->stripe_id;
        $hasBillableAccess = $user->has_lifetime_access
            || $subscriptionStatus === 'active'
            || ($subscriptionStatus === 'trialing' && filled($subscriptionStripeId));

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
                'detail' => $user->has_lifetime_access
                    ? ($user->membership_type === 'content_creator' ? 'Creador de contenido' : 'Acceso permanente')
                    : ($subscriptionStatus ?: 'Sin suscripcion'),
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
            'type' => 'nullable|in:cedula,ine,both',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $user = User::findOrFail($id);
        $type = $request->type ?? 'both';

        if ($request->action === 'approve') {
            if (! $user->cedula_selfie_url || ! $user->ine_selfie_url) {
                throw ValidationException::withMessages([
                    'identity' => 'Para aprobar la identidad, el psicólogo debe tener cargadas la cédula profesional y el INE.',
                ]);
            }

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
            $documents = [
                'cedula' => 'tu cédula profesional',
                'ine' => 'tu INE',
                'both' => 'tu cédula profesional e INE',
            ];

            if ($type === 'cedula' || $type === 'both') {
                $user->cedula_selfie_url = null;
            }

            if ($type === 'ine' || $type === 'both') {
                $user->ine_selfie_url = null;
            }

            $user->identity_verification_status = $type === 'both' ? 'rejected' : 'pending';
            $documentType = $documents[$type] ?? $documents['both'];
            $rejectionReason = trim((string) $request->input('rejection_reason', ''));
            $message = 'Documento rechazado. El usuario deberá subir nuevamente ' . $documentType . '.';

            // Enviar email de rechazo
            EmailService::send(
                $user->email,
                'Verificación de Identidad - Acción Requerida - MindMeet',
                'email.identity-rejected',
                [
                    'name' => $user->name,
                    'documentType' => $documentType,
                    'rejectionReason' => $rejectionReason,
                    'url' => rtrim(
                        config('app.front_url_psicologo') ?: config('app.front_url') ?: config('app.frontend_url'),
                        '/'
                    ) . '/dashboard'
                ]
            );
        }

        $user->save();

        return redirect()->route('psicologoShow', $user->id)->with('status', $message);
    }
}
