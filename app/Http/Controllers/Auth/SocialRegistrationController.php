<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\SocialRegistrationIntent;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\NuevoPsicologoRegistrado;
use App\Services\OrganizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SocialRegistrationController extends Controller
{
    private const PHONE_REGEX = '/^\d{10}$/';

    public function show(string $token)
    {
        $intent = $this->findIntent($token);

        return response()->json([
            'name' => $intent->name,
            'email' => $intent->email,
            'avatar' => $intent->avatar,
            'provider' => $intent->provider_name,
        ]);
    }

    public function complete(Request $request, OrganizationService $organizationService)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'telefono' => ['required', 'regex:' . self::PHONE_REGEX],
        ], ['telefono.regex' => 'El teléfono debe tener exactamente 10 dígitos.']);

        $user = DB::transaction(function () use ($data, $organizationService) {
            $intent = SocialRegistrationIntent::query()
                ->where('token_hash', hash('sha256', $data['token']))
                ->lockForUpdate()
                ->first();

            if (! $intent || $intent->completed_at || $intent->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'token' => ['Esta solicitud de registro expiró. Vuelve a continuar con Google.'],
                ]);
            }

            if (User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($intent->email)])->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['Ya existe una cuenta con este correo. Inicia sesión para continuar.'],
                ]);
            }

            $user = User::create([
                'name' => $intent->name,
                'email' => mb_strtolower($intent->email),
                'provider_name' => $intent->provider_name,
                'provider_id' => $intent->provider_id,
                'avatar' => $intent->avatar,
                'contacto' => ['telefono' => $data['telefono']],
                'configurations' => [
                    'workspace_type' => 'independent',
                    'registration_mode' => 'social',
                ],
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => now(),
            ]);

            $this->ensureInitialWorkspace($user, $organizationService);
            Subscription::firstOrCreate(['user_id' => $user->id], [
                'stripe_id' => null,
                'stripe_plan' => null,
                'stripe_status' => 'init',
                'trial_ends_at' => null,
                'ends_at' => null,
            ]);

            $intent->forceFill(['completed_at' => now()])->save();

            return $user;
        });

        $user->notify(new NuevoPsicologoRegistrado($user, true));

        return response()->json([
            'message' => 'Tu cuenta fue creada. Elige un plan para continuar.',
            'token' => $user->createToken('user_token')->plainTextToken,
        ], 201);
    }

    public function completeExistingPhone(Request $request, OrganizationService $organizationService)
    {
        $data = $request->validate([
            'telefono' => ['required', 'regex:' . self::PHONE_REGEX],
        ], ['telefono.regex' => 'El teléfono debe tener exactamente 10 dígitos.']);

        $user = $request->user();
        $contacto = is_array($user->contacto) ? $user->contacto : [];
        $user->forceFill(['contacto' => array_merge($contacto, ['telefono' => $data['telefono']])])->save();
        $this->ensureInitialWorkspace($user, $organizationService);
        Subscription::firstOrCreate(['user_id' => $user->id], [
            'stripe_status' => 'init',
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

        return response()->json(['message' => 'Teléfono guardado correctamente.', 'user' => $user->fresh('subscription')]);
    }

    private function findIntent(string $token): SocialRegistrationIntent
    {
        $intent = SocialRegistrationIntent::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        abort_unless($intent && ! $intent->completed_at && $intent->expires_at->isFuture(), 410, 'Esta solicitud de registro expiró. Vuelve a continuar con Google.');

        return $intent;
    }

    private function ensureInitialWorkspace(User $user, OrganizationService $organizationService): void
    {
        if ($user->organizationMemberships()->exists()) {
            return;
        }

        $organization = $organizationService->create($user, [
            'name' => $user->name ?: "Consultorio {$user->id}",
            'type' => Organization::TYPE_INDIVIDUAL,
            'settings' => ['created_from' => 'social_registration'],
        ]);

        $configurations = $user->configurations ?? [];
        $configurations['active_organization_id'] = $organization->id;
        $configurations['workspace_type'] = 'independent';
        $configurations['registration_mode'] = $configurations['registration_mode'] ?? 'social';
        $user->forceFill(['configurations' => $configurations])->save();
    }
}
