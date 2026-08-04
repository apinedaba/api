<?php

namespace App\Http\Controllers;

use App\Models\GuardianAccount;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuardianAccountController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:guardian_accounts,email'],
            'phone' => ['nullable', 'string', 'max:20'], 'password' => ['required', 'string', 'min:8'],
        ]);
        $guardian = GuardianAccount::create([...$data, 'email' => Str::lower(trim($data['email'])), 'email_verified_at' => now()]);
        $this->claimKnownRelatives($guardian);
        $this->ensureSelfPatient($guardian);
        $token = $guardian->createToken('guardian_token')->plainTextToken;
        return response()->json(['token' => $token, 'user' => $this->sessionPayload($guardian), 'account_type' => 'guardian'], 201);
    }

    public function index(Request $request)
    {
        $guardian = $this->guardian($request);
        return response()->json($this->sessionPayload($guardian));
    }

    public function storeRelative(Request $request)
    {
        $guardian = $this->guardian($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before_or_equal:' . now()->subYears(2)->toDateString()],
            'relationship' => ['required', 'string', 'max:100'],
            'can_sign' => ['required', 'boolean'], 'representation_reason' => ['nullable', 'string', 'max:255'],
        ], ['birth_date.before_or_equal' => 'La persona debe tener al menos 2 años cumplidos.']);
        $isMinor = now()->diffInYears($data['birth_date']) < 18;
        if (! $isMinor && blank($data['representation_reason'])) abort(422, 'Indica por qué el adulto requiere un representante.');
        $patient = Patient::create([
            'name' => trim($data['name']), 'password' => Hash::make(Str::random(40)), 'registration_source' => 'guardian',
            'relevantes' => ['fechaNac' => $data['birth_date']],
            'relationships' => [[
                'nombre' => $guardian->name, 'parentesco' => $data['relationship'], 'correo' => $guardian->email,
                'telefono' => $guardian->phone, 'es_contacto_emergencia' => true,
                'es_representante_legal' => (bool) $data['can_sign'],
            ]],
        ]);
        $guardian->patients()->attach($patient->id, [
            'relationship' => $data['relationship'], 'can_manage' => true, 'can_sign' => $data['can_sign'],
            'representation_reason' => $data['representation_reason'] ?? ($isMinor ? 'Persona menor de edad' : null), 'status' => 'active',
        ]);
        return response()->json($this->sessionPayload($guardian->fresh()), 201);
    }

    public function sessionPayload(GuardianAccount $guardian): array
    {
        $this->ensureSelfPatient($guardian);
        $guardian->load(['patients' => fn ($query) => $query->wherePivot('status', 'active')]);
        return [
            'id' => $guardian->id, 'name' => $guardian->name, 'email' => $guardian->email, 'phone' => $guardian->phone,
            'account_type' => 'guardian',
            'represented_patients' => $guardian->patients->map(fn ($patient) => [
                'id' => $patient->id, 'name' => $patient->name, 'image' => $patient->image,
                'birth_date' => data_get($patient->relevantes, 'fechaNac'), 'relationship' => $patient->pivot->relationship,
                'can_manage' => (bool) $patient->pivot->can_manage, 'can_sign' => (bool) $patient->pivot->can_sign,
                'is_self' => $patient->pivot->relationship === 'Titular',
            ])->values(),
        ];
    }

    private function guardian(Request $request): GuardianAccount
    {
        abort_unless($request->user() instanceof GuardianAccount, 403, 'Esta función requiere una cuenta de representante.');
        return $request->user();
    }

    private function claimKnownRelatives(GuardianAccount $guardian): void
    {
        Patient::whereNotNull('relationships')->get()->each(function (Patient $patient) use ($guardian) {
            $relationship = collect($patient->relationships ?? [])->first(fn ($item) =>
                Str::lower(trim((string) data_get($item, 'correo'))) === $guardian->email
                && filter_var(data_get($item, 'es_representante_legal'), FILTER_VALIDATE_BOOLEAN));
            if ($relationship) $guardian->patients()->syncWithoutDetaching([$patient->id => [
                'relationship' => data_get($relationship, 'parentesco', 'Representante legal'), 'can_manage' => true,
                'can_sign' => true, 'representation_reason' => 'Representante registrado por el profesional', 'status' => 'active',
            ]]);
        });
    }

    private function ensureSelfPatient(GuardianAccount $guardian): void
    {
        $alreadyLinked = $guardian->patients()->wherePivot('relationship', 'Titular')->exists();
        if ($alreadyLinked) return;

        $normalizedName = Str::lower(trim($guardian->name));
        $patient = Patient::whereRaw('LOWER(email) = ?', [$guardian->email])
            ->get()
            ->first(fn (Patient $candidate) => Str::lower(trim($candidate->name)) === $normalizedName);

        if (! $patient) {
            $emailIsUsed = Patient::whereRaw('LOWER(email) = ?', [$guardian->email])->exists();
            $patient = Patient::create([
                'name' => $guardian->name,
                'email' => $emailIsUsed ? null : $guardian->email,
                'phone' => $guardian->phone,
                'password' => $guardian->password,
                'registration_source' => 'guardian_self',
                'contacto' => ['correo' => $guardian->email, 'telefono' => $guardian->phone],
                'relationships' => [],
            ]);
        }

        $guardian->patients()->syncWithoutDetaching([$patient->id => [
            'relationship' => 'Titular',
            'can_manage' => true,
            'can_sign' => true,
            'representation_reason' => 'Perfil propio',
            'status' => 'active',
        ]]);
    }
}
