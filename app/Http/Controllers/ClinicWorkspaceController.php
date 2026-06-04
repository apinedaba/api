<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\PatientUser;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\NuevoPsicologoRegistrado;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClinicWorkspaceController extends Controller
{
    private const MODULE_KEYS = [
        'dashboard',
        'leads',
        'patients',
        'agenda',
        'questionnaires',
        'contracts',
        'payments',
        'library',
        'community',
        'help',
        'clinic_workspace',
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        $clinics = Clinic::query()
            ->with(['owner:id,name,email', 'memberships.user:id,name,email,image,activo'])
            ->where(function ($query) use ($user) {
                $query->where('owner_user_id', $user->id)
                    ->orWhereHas('memberships', function ($membershipQuery) use ($user) {
                        $membershipQuery->where('user_id', $user->id);
                    });
            })
            ->latest()
            ->get()
            ->map(fn (Clinic $clinic) => $this->serializeClinicSummary($clinic));

        return response()->json($clinics);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $clinicAccess = $user->resolveClinicAccess();

        if (!$clinicAccess['is_clinic_account']) {
            return response()->json([
                'message' => 'Solo las cuentas tipo clínica pueden administrar este workspace.',
            ], 403);
        }

        if (!$clinicAccess['can_create_additional_clinics']) {
            return response()->json([
                'message' => 'Tu plan actual crea una sola clínica durante el registro. Para abrir más clínicas necesitas el plan Multiclínica.',
                'errors' => [
                    'clinic_plan' => ['Tu plan actual no permite crear clínicas adicionales desde el panel.'],
                ],
            ], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ]);

        $clinic = Clinic::create([
            'owner_user_id' => $user->id,
            'name' => $data['name'],
            'slug' => $this->generateUniqueSlug($data['name']),
            'account_type' => 'clinic',
            'status' => 'active',
            'description' => $data['description'] ?? null,
            'base_psychologist_limit' => 6,
            'addon_psychologist_slots' => 0,
            'contact' => $data['contact'] ?? null,
            'settings' => array_merge($data['settings'] ?? [], [
                'multiclinic_managed' => true,
                'unlimited_psychologists' => true,
            ]),
        ]);

        $this->upsertMembership(
            $clinic,
            $user->id,
            'owner',
            true,
            true,
            true,
            true,
            $this->defaultOwnerModules()
        );

        return response()->json([
            'message' => 'Clínica creada correctamente.',
            'clinic' => $this->serializeClinicDetail($clinic->fresh(['owner', 'memberships.user', 'memberships.clinic'])),
        ], 201);
    }

    public function show(Request $request, Clinic $clinic)
    {
        $this->authorizeView($request->user(), $clinic);

        $clinic->load([
            'owner:id,name,email',
            'memberships.user:id,name,email,image,contacto,activo,identity_verification_status,configurations',
            'memberships.clinic:id,name,slug',
        ]);

        return response()->json($this->serializeClinicDetail($clinic));
    }

    public function update(Request $request, Clinic $clinic)
    {
        $membership = $this->authorizeManage($request->user(), $clinic);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
            'contact' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ]);

        $payload = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'slug' => $clinic->name !== $data['name'] ? $this->generateUniqueSlug($data['name'], $clinic->id) : $clinic->slug,
            'contact' => $data['contact'] ?? $clinic->contact,
            'settings' => $data['settings'] ?? $clinic->settings,
        ];

        if ($membership?->role === 'owner') {
            $payload['status'] = $data['status'] ?? $clinic->status;
        }

        $clinic->update($payload);

        return response()->json([
            'message' => 'Clínica actualizada correctamente.',
            'clinic' => $this->serializeClinicDetail($clinic->fresh(['owner', 'memberships.user', 'memberships.clinic'])),
        ]);
    }

    public function storePsychologist(Request $request, Clinic $clinic)
    {
        $this->authorizeManage($request->user(), $clinic);

        if (!$clinic->hasUnlimitedPsychologistCapacity() && $clinic->remainingPsychologistSlots() <= 0) {
            return response()->json([
                'message' => 'Esta membresía de clínica ya alcanzó su límite de psicólogos. Solicita addons antes de registrar a otro profesional.',
                'errors' => [
                    'clinic_capacity' => ['No hay espacios disponibles en tu plan actual.'],
                ],
            ], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telefono' => ['required', 'regex:/^\d{10}$/'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['nullable', 'in:psychologist,manager,assistant'],
            'is_primary' => ['nullable', 'boolean'],
            'can_manage_schedule' => ['nullable', 'boolean'],
            'can_manage_patients' => ['nullable', 'boolean'],
            'can_view_finance' => ['nullable', 'boolean'],
            'allowed_modules' => ['nullable', 'array'],
            'allowed_modules.*' => ['string'],
        ]);

        $user = User::create([
            'name' => trim((string) $data['name']),
            'email' => mb_strtolower(trim((string) $data['email'])),
            'contacto' => [
                'telefono' => preg_replace('/\D+/', '', (string) $data['telefono']),
            ],
            'configurations' => [
                'workspace_type' => 'clinic_member',
                'registration_mode' => 'clinic_created',
                'clinic_managed' => true,
                'clinic_id' => $clinic->id,
            ],
            'password' => Hash::make($data['password']),
            'verification_code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'code_expires_at' => now()->addMinutes(10),
        ]);

        Subscription::firstOrCreate(
            ['user_id' => $user->id],
            [
                'stripe_id' => null,
                'stripe_plan' => null,
                'stripe_status' => 'clinic_managed',
                'trial_ends_at' => null,
                'ends_at' => null,
            ]
        );

        $role = $data['role'] ?? 'psychologist';
        $allowedModules = $this->resolveAllowedModules($data['allowed_modules'] ?? null, $role);

        if (($data['is_primary'] ?? false) === true) {
            ClinicMembership::query()
                ->where('user_id', $user->id)
                ->update(['is_primary' => false]);
        }

        $this->upsertMembership(
            $clinic,
            $user->id,
            $role,
            (bool) ($data['is_primary'] ?? true),
            (bool) ($data['can_manage_schedule'] ?? true),
            (bool) ($data['can_manage_patients'] ?? true),
            (bool) ($data['can_view_finance'] ?? false),
            $allowedModules
        );

        try {
            $user->notify(new NuevoPsicologoRegistrado($user, true));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'message' => 'Psicólogo registrado dentro de la clínica.',
            'clinic' => $this->serializeClinicDetail($clinic->fresh(['owner', 'memberships.user', 'memberships.clinic'])),
        ], 201);
    }

    public function updatePsychologist(Request $request, Clinic $clinic, User $user)
    {
        $this->authorizeManage($request->user(), $clinic);

        $membership = ClinicMembership::query()
            ->where('clinic_id', $clinic->id)
            ->where('user_id', $user->id)
            ->where('role', 'psychologist')
            ->firstOrFail();

        $data = $request->validate([
            'can_manage_schedule' => ['nullable', 'boolean'],
            'can_manage_patients' => ['nullable', 'boolean'],
            'can_view_finance' => ['nullable', 'boolean'],
            'allowed_modules' => ['nullable', 'array'],
            'allowed_modules.*' => ['string'],
        ]);

        $membership->update([
            'can_manage_schedule' => (bool) ($data['can_manage_schedule'] ?? $membership->can_manage_schedule),
            'can_manage_patients' => (bool) ($data['can_manage_patients'] ?? $membership->can_manage_patients),
            'can_view_finance' => (bool) ($data['can_view_finance'] ?? $membership->can_view_finance),
            'meta' => array_merge($membership->meta ?? [], [
                'allowed_modules' => $this->resolveAllowedModules($data['allowed_modules'] ?? data_get($membership->meta, 'allowed_modules'), 'psychologist'),
            ]),
        ]);

        return response()->json([
            'message' => 'Permisos del psicólogo actualizados.',
            'clinic' => $this->serializeClinicDetail($clinic->fresh(['owner', 'memberships.user', 'memberships.clinic'])),
        ]);
    }

    public function detachPsychologist(Request $request, Clinic $clinic, User $user)
    {
        $this->authorizeManage($request->user(), $clinic);

        ClinicMembership::query()
            ->where('clinic_id', $clinic->id)
            ->where('user_id', $user->id)
            ->where('role', 'psychologist')
            ->delete();

        return response()->json([
            'message' => 'Psicólogo removido de la clínica.',
            'clinic' => $this->serializeClinicDetail($clinic->fresh(['owner', 'memberships.user', 'memberships.clinic'])),
        ]);
    }

    protected function authorizeView(User $user, Clinic $clinic): void
    {
        if ($clinic->owner_user_id === $user->id) {
            return;
        }

        $membership = ClinicMembership::query()
            ->where('clinic_id', $clinic->id)
            ->where('user_id', $user->id)
            ->first();

        abort_unless(
            $membership && in_array($membership->role, ['owner', 'manager', 'assistant'], true),
            403,
            'No tienes acceso a esta clínica.'
        );
    }

    protected function authorizeManage(User $user, Clinic $clinic): ?ClinicMembership
    {
        if ($clinic->owner_user_id === $user->id) {
            return ClinicMembership::query()
                ->where('clinic_id', $clinic->id)
                ->where('user_id', $user->id)
                ->first();
        }

        $membership = ClinicMembership::query()
            ->where('clinic_id', $clinic->id)
            ->where('user_id', $user->id)
            ->first();

        abort_unless(
            $membership && in_array($membership->role, ['owner', 'manager'], true),
            403,
            'No tienes permisos para gestionar esta clínica.'
        );

        return $membership;
    }

    protected function buildClinicAppointmentsQuery(Clinic $clinic, Collection $psychologistIds)
    {
        return Appointment::query()
            ->where(function ($query) use ($clinic, $psychologistIds) {
                $query->where('clinic_id', $clinic->id);

                if ($psychologistIds->isNotEmpty()) {
                    $query->orWhere(function ($fallbackQuery) use ($psychologistIds) {
                        $fallbackQuery
                            ->whereNull('clinic_id')
                            ->whereIn('user', $psychologistIds->all());
                    });
                }
            });
    }

    protected function buildClinicPatientRelationsQuery(Clinic $clinic, Collection $psychologistIds)
    {
        return PatientUser::query()
            ->where(function ($query) use ($clinic, $psychologistIds) {
                $query->where('clinic_id', $clinic->id);

                if ($psychologistIds->isNotEmpty()) {
                    $query->orWhere(function ($fallbackQuery) use ($psychologistIds) {
                        $fallbackQuery
                            ->whereNull('clinic_id')
                            ->whereIn('user', $psychologistIds->all());
                    });
                }
            });
    }

    protected function upsertMembership(
        Clinic $clinic,
        int $userId,
        string $role,
        bool $isPrimary,
        bool $canManageSchedule,
        bool $canManagePatients,
        bool $canViewFinance,
        array $allowedModules
    ): ClinicMembership {
        return ClinicMembership::updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'user_id' => $userId,
            ],
            [
                'role' => $role,
                'is_primary' => $isPrimary,
                'can_manage_schedule' => $canManageSchedule,
                'can_manage_patients' => $canManagePatients,
                'can_view_finance' => $canViewFinance,
                'meta' => [
                    'allowed_modules' => $allowedModules,
                ],
            ]
        );
    }

    protected function generateUniqueSlug(string $name, ?int $ignoreClinicId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'clinica';
        $slug = $baseSlug;
        $counter = 2;

        while (
            Clinic::query()
                ->when($ignoreClinicId, fn ($query) => $query->where('id', '!=', $ignoreClinicId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    protected function resolveAllowedModules(?array $modules, string $role): array
    {
        $modules = is_array($modules) ? $modules : [];
        $modules = array_values(array_intersect(self::MODULE_KEYS, $modules));

        if ($role !== 'psychologist') {
            return array_values(array_unique(array_merge($modules, $this->defaultOwnerModules())));
        }

        if (empty($modules)) {
            return $this->defaultPsychologistModules();
        }

        $forcedModules = ['dashboard', 'help'];
        return array_values(array_unique(array_merge($modules, $forcedModules)));
    }

    protected function defaultOwnerModules(): array
    {
        return self::MODULE_KEYS;
    }

    protected function defaultPsychologistModules(): array
    {
        return [
            'dashboard',
            'leads',
            'patients',
            'agenda',
            'questionnaires',
            'contracts',
            'payments',
            'library',
            'community',
            'help',
        ];
    }

    protected function serializeClinicSummary(Clinic $clinic): array
    {
        $memberships = $clinic->memberships ?? collect();
        $psychologistMemberships = $memberships->where('role', 'psychologist');

        return [
            'id' => $clinic->id,
            'name' => $clinic->name,
            'slug' => $clinic->slug,
            'account_type' => $clinic->account_type,
            'status' => $clinic->status,
            'description' => $clinic->description,
            'base_psychologist_limit' => $clinic->base_psychologist_limit,
            'addon_psychologist_slots' => $clinic->addon_psychologist_slots,
            'capacity_total' => $clinic->hasUnlimitedPsychologistCapacity() ? null : $clinic->psychologistCapacity(),
            'capacity_used' => $clinic->activePsychologistCount(),
            'capacity_remaining' => $clinic->hasUnlimitedPsychologistCapacity() ? null : $clinic->remainingPsychologistSlots(),
            'capacity_is_unlimited' => $clinic->hasUnlimitedPsychologistCapacity(),
            'owner' => $clinic->owner ? [
                'id' => $clinic->owner->id,
                'name' => $clinic->owner->name,
                'email' => $clinic->owner->email,
            ] : null,
            'psychologists' => $psychologistMemberships
                ->filter(fn (ClinicMembership $membership) => $membership->user)
                ->map(fn (ClinicMembership $membership) => $this->serializeMembership($membership))
                ->values(),
        ];
    }

    protected function serializeClinicDetail(Clinic $clinic): array
    {
        $psychologistMemberships = $clinic->memberships
            ->where('role', 'psychologist')
            ->values();

        $psychologistIds = $psychologistMemberships
            ->pluck('user_id')
            ->filter()
            ->values();

        $appointments = $this->buildClinicAppointmentsQuery($clinic, $psychologistIds)
            ->with(['user:id,name,email,image', 'patient:id,name,email,phone'])
            ->orderBy('start')
            ->limit(300)
            ->get()
            ->map(function (Appointment $appointment) {
                $professional = $appointment->relationLoaded('user')
                    ? $appointment->getRelation('user')
                    : $appointment->user()->first();
                $patient = $appointment->relationLoaded('patient')
                    ? $appointment->getRelation('patient')
                    : $appointment->patient()->first();

                return [
                    'id' => $appointment->id,
                    'title' => $appointment->title,
                    'start' => optional($appointment->start)->toIso8601String(),
                    'end' => optional($appointment->end)->toIso8601String(),
                    'state' => $appointment->state,
                    'professional_id' => $professional?->id,
                    'professional' => $professional?->name,
                    'professional_email' => $professional?->email,
                    'patient' => $patient?->name,
                    'patient_id' => $patient?->id,
                    'patient_email' => $patient?->email,
                    'patient_phone' => $patient?->phone,
                    'clinic_id' => $appointment->clinic_id,
                    'extendedProps' => $appointment->extendedProps ?? [],
                ];
            })
            ->values();

        $patientsCount = $this->buildClinicPatientRelationsQuery($clinic, $psychologistIds)
            ->distinct('patient')
            ->count('patient');

        return [
            ...$this->serializeClinicSummary($clinic),
            'contact' => $clinic->contact,
            'settings' => $clinic->settings,
            'metrics' => [
                'psychologists' => $clinic->activePsychologistCount(),
                'patients' => $patientsCount,
                'appointments_total' => $this->buildClinicAppointmentsQuery($clinic, $psychologistIds)->count(),
                'appointments_upcoming' => $this->buildClinicAppointmentsQuery($clinic, $psychologistIds)->where('start', '>=', now())->count(),
                'appointments_today' => $this->buildClinicAppointmentsQuery($clinic, $psychologistIds)
                    ->whereBetween('start', [now()->startOfDay(), now()->endOfDay()])
                    ->count(),
            ],
            'memberships' => $clinic->memberships
                ->sortBy(fn (ClinicMembership $membership) => $membership->role === 'psychologist' ? 0 : 1)
                ->map(fn (ClinicMembership $membership) => $this->serializeMembership($membership))
                ->values(),
            'appointments' => $appointments,
        ];
    }

    protected function serializeMembership(ClinicMembership $membership): array
    {
        return [
            'id' => $membership->id,
            'user_id' => $membership->user_id,
            'role' => $membership->role,
            'is_primary' => $membership->is_primary,
            'can_manage_schedule' => $membership->can_manage_schedule,
            'can_manage_patients' => $membership->can_manage_patients,
            'can_view_finance' => $membership->can_view_finance,
            'allowed_modules' => array_values(array_unique(data_get($membership->meta, 'allowed_modules', []))),
            'user' => $membership->user ? [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'image' => $membership->user->image,
                'activo' => $membership->user->activo,
                'identity_verification_status' => $membership->user->identity_verification_status,
                'telefono' => data_get($membership->user->contacto, 'telefono'),
                'workspace_type' => data_get($membership->user->configurations, 'workspace_type'),
            ] : null,
        ];
    }
}
