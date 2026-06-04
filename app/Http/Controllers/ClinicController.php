<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\PatientUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ClinicController extends Controller
{
    public function index()
    {
        $clinics = Clinic::query()
            ->with(['owner:id,name,email', 'memberships.user:id,name,email,image'])
            ->withCount('memberships')
            ->latest()
            ->get()
            ->map(fn (Clinic $clinic) => $this->mapClinicRow($clinic));

        return Inertia::render('Clinicas', [
            'clinics' => $clinics,
            'owners' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'psychologists' => User::query()->with('clinicMemberships')->orderBy('name')->get(['id', 'name', 'email', 'image', 'activo']),
        ]);
    }

    public function show(Clinic $clinic)
    {
        $clinic->load([
            'owner:id,name,email',
            'memberships.user:id,name,email,image,contacto,activo,identity_verification_status',
        ]);

        $psychologistIds = $clinic->memberships
            ->pluck('user_id')
            ->filter()
            ->values();

        $appointments = $this->buildClinicAppointmentsQuery($clinic, $psychologistIds)
            ->with(['user:id,name,email,image', 'patient:id,name,email,phone'])
            ->orderBy('start')
            ->limit(300)
            ->get()
            ->map(function (Appointment $appointment) {
                return [
                    'id' => $appointment->id,
                    'title' => $appointment->title,
                    'start' => optional($appointment->start)->toIso8601String(),
                    'end' => optional($appointment->end)->toIso8601String(),
                    'state' => $appointment->state,
                    'professional' => $appointment->user?->name,
                    'patient' => $appointment->patient?->name,
                    'patient_email' => $appointment->patient?->email,
                    'patient_phone' => $appointment->patient?->phone,
                    'clinic_id' => $appointment->clinic_id,
                    'extendedProps' => $appointment->extendedProps ?? [],
                ];
            });

        $patientCount = $this->buildClinicPatientRelationsQuery($clinic, $psychologistIds)
            ->distinct('patient')
            ->count('patient');

        $metrics = [
            'psychologists' => $psychologistIds->count(),
            'patients' => $patientCount,
            'appointments_total' => $this->buildClinicAppointmentsQuery($clinic, $psychologistIds)->count(),
            'appointments_upcoming' => $this->buildClinicAppointmentsQuery($clinic, $psychologistIds)
                ->where('start', '>=', now())
                ->count(),
            'appointments_today' => $this->buildClinicAppointmentsQuery($clinic, $psychologistIds)
                ->whereBetween('start', [now()->startOfDay(), now()->endOfDay()])
                ->count(),
        ];

        return Inertia::render('Clinicas/Show', [
            'clinic' => [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'slug' => $clinic->slug,
                'account_type' => $clinic->account_type,
                'status' => $clinic->status,
                'base_psychologist_limit' => $clinic->base_psychologist_limit,
                'addon_psychologist_slots' => $clinic->addon_psychologist_slots,
                'capacity_total' => $clinic->psychologistCapacity(),
                'capacity_used' => $clinic->activePsychologistCount(),
                'capacity_remaining' => $clinic->remainingPsychologistSlots(),
                'description' => $clinic->description,
                'contact' => $clinic->contact,
                'settings' => $clinic->settings,
                'owner' => $clinic->owner,
                'memberships' => $clinic->memberships->map(function (ClinicMembership $membership) {
                    return [
                        'id' => $membership->id,
                        'user_id' => $membership->user_id,
                        'role' => $membership->role,
                        'is_primary' => $membership->is_primary,
                        'can_manage_schedule' => $membership->can_manage_schedule,
                        'can_manage_patients' => $membership->can_manage_patients,
                        'can_view_finance' => $membership->can_view_finance,
                        'user' => $membership->user,
                    ];
                }),
            ],
            'metrics' => $metrics,
            'appointments' => $appointments,
            'psychologists' => User::query()->with('clinicMemberships')->orderBy('name')->get(['id', 'name', 'email', 'image', 'activo']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'base_psychologist_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'addon_psychologist_slots' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'description' => ['nullable', 'string'],
            'contact' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ]);

        $clinic = Clinic::create([
            ...$data,
            'slug' => $this->generateUniqueSlug($data['name']),
            'account_type' => 'clinic',
            'status' => $data['status'] ?? 'active',
            'base_psychologist_limit' => $data['base_psychologist_limit'] ?? 6,
            'addon_psychologist_slots' => $data['addon_psychologist_slots'] ?? 0,
        ]);

        if ($clinic->owner_user_id) {
            $this->upsertMembership($clinic, (int) $clinic->owner_user_id, 'owner', true, true, true, true);
        }

        return redirect()->route('clinics.show', $clinic)->with('status', 'Clinica creada correctamente.');
    }

    public function update(Request $request, Clinic $clinic)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'base_psychologist_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'addon_psychologist_slots' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'description' => ['nullable', 'string'],
            'contact' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ]);

        $clinic->update([
            ...$data,
            'slug' => $clinic->name !== $data['name']
                ? $this->generateUniqueSlug($data['name'], $clinic->id)
                : $clinic->slug,
        ]);

        if ($clinic->owner_user_id) {
            $this->upsertMembership($clinic, (int) $clinic->owner_user_id, 'owner', true, true, true, true);
        }

        return redirect()->route('clinics.show', $clinic)->with('status', 'Clinica actualizada correctamente.');
    }

    public function attachPsychologist(Request $request, Clinic $clinic)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['nullable', 'string', 'max:50'],
            'is_primary' => ['nullable', 'boolean'],
            'can_manage_schedule' => ['nullable', 'boolean'],
            'can_manage_patients' => ['nullable', 'boolean'],
            'can_view_finance' => ['nullable', 'boolean'],
        ]);

        $role = $data['role'] ?? 'psychologist';
        $isPrimary = (bool) ($data['is_primary'] ?? false);
        $existingMembership = ClinicMembership::query()
            ->where('clinic_id', $clinic->id)
            ->where('user_id', $data['user_id'])
            ->first();

        if (!$existingMembership && $clinic->remainingPsychologistSlots() <= 0) {
            return redirect()
                ->route('clinics.show', $clinic)
                ->withErrors([
                    'clinic_capacity' => 'Esta membresia de clinica ya alcanzo su limite de psicologos. Agrega addons de espacios antes de vincular a otro profesional.',
                ]);
        }

        if ($isPrimary) {
            ClinicMembership::query()
                ->where('user_id', $data['user_id'])
                ->update(['is_primary' => false]);
        }

        $membership = $this->upsertMembership(
            $clinic,
            (int) $data['user_id'],
            $role,
            $isPrimary || !$this->userHasPrimaryMembership((int) $data['user_id']),
            (bool) ($data['can_manage_schedule'] ?? in_array($role, ['owner', 'manager', 'assistant'], true)),
            (bool) ($data['can_manage_patients'] ?? in_array($role, ['owner', 'manager', 'assistant'], true)),
            (bool) ($data['can_view_finance'] ?? in_array($role, ['owner', 'manager'], true)),
        );

        PatientUser::query()
            ->where('user', $membership->user_id)
            ->whereNull('clinic_id')
            ->update(['clinic_id' => $clinic->id]);

        Appointment::query()
            ->where('user', $membership->user_id)
            ->whereNull('clinic_id')
            ->update(['clinic_id' => $clinic->id]);

        return redirect()->route('clinics.show', $clinic)->with('status', 'Psicologo vinculado a la clinica.');
    }

    public function detachPsychologist(Clinic $clinic, User $user)
    {
        ClinicMembership::query()
            ->where('clinic_id', $clinic->id)
            ->where('user_id', $user->id)
            ->delete();

        return redirect()->route('clinics.show', $clinic)->with('status', 'Psicologo removido de la clinica.');
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
        bool $canViewFinance
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
            ]
        );
    }

    protected function userHasPrimaryMembership(int $userId): bool
    {
        return ClinicMembership::query()
            ->where('user_id', $userId)
            ->where('is_primary', true)
            ->exists();
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

    protected function mapClinicRow(Clinic $clinic): array
    {
        $memberships = $clinic->memberships ?? collect();
        $psychologists = $memberships
            ->filter(fn (ClinicMembership $membership) => $membership->user)
            ->map(function (ClinicMembership $membership) {
                return [
                    'id' => $membership->user->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'image' => $membership->user->image,
                    'role' => $membership->role,
                ];
            })
            ->values();

        return [
            'id' => $clinic->id,
            'name' => $clinic->name,
            'slug' => $clinic->slug,
            'status' => $clinic->status,
            'description' => $clinic->description,
            'account_type' => $clinic->account_type,
            'base_psychologist_limit' => $clinic->base_psychologist_limit,
            'addon_psychologist_slots' => $clinic->addon_psychologist_slots,
            'capacity_total' => $clinic->psychologistCapacity(),
            'capacity_used' => $clinic->activePsychologistCount(),
            'capacity_remaining' => $clinic->remainingPsychologistSlots(),
            'owner' => $clinic->owner,
            'memberships_count' => $clinic->memberships_count,
            'psychologists' => $psychologists,
        ];
    }
}
