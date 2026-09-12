<?php

namespace App\Http\Controllers;

use App\Models\SellerReferral;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vendedor;
use App\Services\SellerCommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SellerRecoveryController extends Controller
{
    private const RECOVERY_WINDOW_DAYS = 21;
    private const MANUAL_WINDOW_DAYS = 60;

    public function index(SellerCommissionService $commissions): Response
    {
        $commissions->syncAll();

        $recoverySellers = Vendedor::query()
            ->where('status', 'active')
            ->whereIn('sales_mode', ['recovery', 'hybrid'])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'email', 'sales_mode', 'can_register_manual_sales']);

        $candidates = User::query()
            ->with(['subscription', 'sellerReferral.vendedor:id,nombre'])
            ->where('created_at', '<=', now()->subDays(7))
            ->where(function ($query) {
                $query->where('has_lifetime_access', false)
                    ->orWhereNull('has_lifetime_access');
            })
            ->whereDoesntHave('subscription', fn ($query) => $query->where('stripe_status', 'active'))
            ->where(function ($query) {
                $query->whereDoesntHave('sellerReferral')
                    ->orWhereHas('sellerReferral', function ($referralQuery) {
                        $referralQuery
                            ->where('source', SellerReferral::SOURCE_RECOVERY)
                            ->where('claimed_until', '<=', now());
                    });
            })
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (User $user) => $this->mapPsychologist($user));

        $assignments = SellerReferral::query()
            ->with(['vendedor:id,nombre,email', 'user:id,name,email,contacto,isProfileComplete,created_at'])
            ->whereIn('source', [SellerReferral::SOURCE_RECOVERY, SellerReferral::SOURCE_MANUAL])
            ->latest('assigned_at')
            ->limit(150)
            ->get()
            ->map(fn (SellerReferral $referral) => $this->mapOpportunity($referral));

        return Inertia::render('SellerRecovery', [
            'recoverySellers' => $recoverySellers,
            'candidates' => $candidates,
            'assignments' => $assignments,
            'policy' => [
                'recovery_window_days' => self::RECOVERY_WINDOW_DAYS,
                'manual_window_days' => self::MANUAL_WINDOW_DAYS,
            ],
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vendedor_id' => ['required', 'integer', 'exists:vendedores,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $seller = Vendedor::findOrFail($validated['vendedor_id']);
        $user = User::with('subscription')->findOrFail($validated['user_id']);

        if ($seller->status !== 'active' || ! $seller->canRecoverPsychologists()) {
            return back()->withErrors(['vendedor_id' => 'Selecciona un recuperador activo.']);
        }

        if ($this->hasPaidAccess($user)) {
            return back()->withErrors(['user_id' => 'Este psicólogo ya tiene una suscripción activa.']);
        }

        $result = $this->assignRecoveryOpportunity($seller, $user);
        if (! $result['assigned']) {
            return back()->withErrors(['user_id' => $result['reason']]);
        }

        return back()->with('success', "{$user->name} fue asignado a {$seller->nombre}.");
    }

    public function assignBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vendedor_id' => ['required', 'integer', 'exists:vendedores,id'],
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $seller = Vendedor::findOrFail($validated['vendedor_id']);
        if ($seller->status !== 'active' || ! $seller->canRecoverPsychologists()) {
            return back()->withErrors(['vendedor_id' => 'Selecciona un recuperador activo.']);
        }

        $users = User::query()
            ->with('subscription')
            ->whereIn('id', $validated['user_ids'])
            ->get()
            ->keyBy('id');
        $assigned = [];
        $skipped = [];

        DB::transaction(function () use ($validated, $users, $seller, &$assigned, &$skipped) {
            foreach ($validated['user_ids'] as $userId) {
                $user = $users->get($userId);
                if (! $user) {
                    $skipped[] = "#{$userId}";
                    continue;
                }

                $result = $this->assignRecoveryOpportunity($seller, $user);
                if ($result['assigned']) {
                    $assigned[] = $user->name;
                } else {
                    $skipped[] = $user->name;
                }
            }
        });

        $message = count($assigned)." psicólogo(s) asignado(s) a {$seller->nombre}.";
        if (count($skipped)) {
            $message .= ' '.count($skipped).' omitido(s): ya no son elegibles o tienen una cartera protegida.';
        }

        return back()->with('success', $message);
    }

    public function createManual(Request $request): RedirectResponse
    {
        /** @var Vendedor $seller */
        $seller = Auth::guard('vendedor_web')->user();
        abort_unless($seller?->canRecoverPsychologists() && $seller->can_register_manual_sales, 403);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::with('subscription')
            ->where('email', mb_strtolower(trim($validated['email'])))
            ->first();

        if (! $user) {
            return back()->withErrors(['email' => 'No encontramos un psicólogo registrado con ese correo.']);
        }

        if ($this->hasPaidAccess($user)) {
            return back()->withErrors(['email' => 'Este psicólogo ya tiene una suscripción activa.']);
        }

        $existing = SellerReferral::where('user_id', $user->id)->first();
        if ($existing && $existing->vendedor_id !== $seller->id) {
            return back()->withErrors(['email' => 'Este psicólogo ya está protegido por otro vendedor.']);
        }

        $payload = [
            'vendedor_id' => $seller->id,
            'source' => SellerReferral::SOURCE_MANUAL,
            'status' => 'inactive',
            'pipeline_status' => SellerReferral::PIPELINE_ASSIGNED,
            'assigned_at' => now(),
            'claimed_until' => now()->addDays(self::MANUAL_WINDOW_DAYS),
            'contact_note' => $validated['note'] ?? null,
            'metadata' => ['source' => 'manual_sale', 'created_by_seller_id' => $seller->id],
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            SellerReferral::create($payload + [
                'user_id' => $user->id,
                'registered_at' => $user->created_at,
            ]);
        }

        return back()->with('success', "{$user->name} se agregó a tu cartera manual.");
    }

    public function updateFollowUp(Request $request, SellerReferral $sellerReferral): RedirectResponse
    {
        /** @var Vendedor $seller */
        $seller = Auth::guard('vendedor_web')->user();
        abort_unless(
            $sellerReferral->vendedor_id === $seller?->id && $sellerReferral->isRecoveryOpportunity(),
            403
        );

        $validated = $request->validate([
            'pipeline_status' => ['required', 'in:contacted,follow_up,onboarding,awaiting_payment,no_response,not_interested'],
            'contact_channel' => ['nullable', 'in:whatsapp,call,email,other'],
            'note' => ['nullable', 'string', 'max:2000'],
            'next_follow_up_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        if (
            $sellerReferral->next_follow_up_at?->isFuture()
            && in_array($validated['pipeline_status'], ['contacted', 'follow_up'], true)
        ) {
            return back()->withErrors([
                'next_follow_up_at' => 'Este psicólogo tiene seguimiento programado para '.$sellerReferral->next_follow_up_at->format('d/m/Y H:i').'.',
            ]);
        }

        $status = $validated['pipeline_status'];
        $contacted = in_array($status, ['contacted', 'follow_up', 'no_response', 'not_interested'], true);
        $claimedUntil = match ($status) {
            'no_response' => now()->addDays(30),
            'not_interested' => now()->addDays(60),
            default => now()->addDays(self::RECOVERY_WINDOW_DAYS),
        };

        $sellerReferral->update([
            'pipeline_status' => $status,
            'last_contacted_at' => $contacted ? now() : $sellerReferral->last_contacted_at,
            'next_follow_up_at' => $validated['next_follow_up_at'] ?? null,
            'contact_attempts' => $contacted ? $sellerReferral->contact_attempts + 1 : $sellerReferral->contact_attempts,
            'last_contact_channel' => $validated['contact_channel'] ?? $sellerReferral->last_contact_channel,
            'contact_note' => $validated['note'] ?? $sellerReferral->contact_note,
            'claimed_until' => $claimedUntil,
        ]);

        return back()->with('success', 'Seguimiento guardado correctamente.');
    }

    private function canBeAssignedToRecovery(SellerReferral $referral, Vendedor $seller): bool
    {
        if (! $referral->isRecoveryOpportunity()) {
            return false;
        }

        return $referral->vendedor_id === $seller->id
            || ! $referral->claimed_until
            || $referral->claimed_until->isPast();
    }

    private function assignRecoveryOpportunity(Vendedor $seller, User $user): array
    {
        if ($this->hasPaidAccess($user)) {
            return ['assigned' => false, 'reason' => 'Este psicólogo ya tiene una suscripción activa.'];
        }

        $existing = SellerReferral::where('user_id', $user->id)->first();
        if ($existing && ! $this->canBeAssignedToRecovery($existing, $seller)) {
            return ['assigned' => false, 'reason' => 'Este psicólogo ya está protegido por otro vendedor.'];
        }

        $payload = [
            'vendedor_id' => $seller->id,
            'referral_code' => null,
            'source' => SellerReferral::SOURCE_RECOVERY,
            'status' => 'inactive',
            'pipeline_status' => SellerReferral::PIPELINE_ASSIGNED,
            'assigned_at' => now(),
            'claimed_until' => now()->addDays(self::RECOVERY_WINDOW_DAYS),
            'last_contacted_at' => null,
            'next_follow_up_at' => null,
            'contact_attempts' => 0,
            'last_contact_channel' => null,
            'contact_note' => null,
            'metadata' => ['source' => 'recovery_assignment', 'assigned_by' => Auth::id()],
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            SellerReferral::create($payload + [
                'user_id' => $user->id,
                'registered_at' => $user->created_at,
            ]);
        }

        return ['assigned' => true, 'reason' => null];
    }

    private function hasPaidAccess(User $user): bool
    {
        return (bool) $user->has_lifetime_access
            || $user->subscription?->stripe_status === 'active';
    }

    private function mapPsychologist(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $this->psychologistPhone($user),
            'registered_at' => optional($user->created_at)->toDateString(),
            'is_profile_complete' => (bool) $user->isProfileComplete,
            'subscription_status' => $user->subscription?->stripe_status ?: 'sin suscripción',
            'assigned_to' => $user->sellerReferral?->vendedor?->nombre,
            'claimed_until' => optional($user->sellerReferral?->claimed_until)->toDateString(),
        ];
    }

    private function mapOpportunity(SellerReferral $referral): array
    {
        return [
            'id' => $referral->id,
            'source' => $referral->source,
            'pipeline_status' => $referral->pipeline_status,
            'seller' => $referral->vendedor?->only(['id', 'nombre', 'email']),
            'psychologist' => $referral->user ? [
                'id' => $referral->user->id,
                'name' => $referral->user->name,
                'email' => $referral->user->email,
                'phone' => $this->psychologistPhone($referral->user),
                'isProfileComplete' => (bool) $referral->user->isProfileComplete,
                'created_at' => optional($referral->user->created_at)->toDateTimeString(),
            ] : null,
            'claimed_until' => optional($referral->claimed_until)->toDateString(),
            'last_contacted_at' => optional($referral->last_contacted_at)->toDateTimeString(),
            'next_follow_up_at' => optional($referral->next_follow_up_at)->toDateTimeString(),
            'contact_attempts' => $referral->contact_attempts,
        ];
    }

    private function psychologistPhone(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return data_get($user->contacto, 'telefono')
            ?: data_get($user->contacto, 'whatsapp')
            ?: data_get($user->contacto, 'movil')
            ?: data_get($user->contacto, 'mobile');
    }
}
