<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendedorController;
use App\Services\SellerCommissionService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class VendedorDashboardController extends Controller
{
    public function index()
    {
        /** @var \App\Models\Vendedor $vendedor */
        $vendedor = Auth::guard('vendedor_web')->user();

        $vendedor->loadMissing([
            'referrals.user.subscription',
            'commissionItems',
        ]);

        // Resumen financiero
        $pendingBalance  = $vendedor->commissionItems
            ->where('status', 'pending')
            ->sum('amount');

        $paidTotal = $vendedor->commissionItems
            ->where('status', 'paid')
            ->sum('amount');

        $activeCount = $vendedor->referrals->where('status', 'active')->count();
        $recoveryOpportunities = $vendedor->referrals
            ->filter(fn ($referral) => $referral->isRecoveryOpportunity());
        $recoveredThisMonth = $recoveryOpportunities
            ->filter(fn ($referral) => $referral->first_activated_at?->isSameMonth(now()))
            ->count();
        $recoveryCommissionRate = app(SellerCommissionService::class)
            ->recoveryCommissionAmount($recoveredThisMonth);

        // Proyección: referidos activos × $20 (milestone month_2 como proxy mensual)
        $nextProjection = $activeCount * SellerCommissionService::COMMISSIONS['month_2'];

        // Métricas de referidos
        $referralsCount = $vendedor->referrals->count();
        $unpaidCount    = $vendedor->referrals->where('status', '!=', 'active')->count();

        // Mapeo de referidos para la tabla
        $referrals = $vendedor->referrals->map(function ($referral) {
            return [
                'id'                  => $referral->id,
                'status'              => $referral->status,
                'registered_at'       => optional($referral->registered_at)->toDateString(),
                'trial_ends_at'       => optional($referral->trial_ends_at)->toDateString(),
                'first_activated_at'  => optional($referral->first_activated_at)->toDateString(),
                'source' => $referral->source,
                'pipeline_status' => $referral->pipeline_status,
                'claimed_until' => optional($referral->claimed_until)->toDateString(),
                'last_contacted_at' => optional($referral->last_contacted_at)->toDateTimeString(),
                'next_follow_up_at' => optional($referral->next_follow_up_at)->format('Y-m-d\\TH:i'),
                'contact_attempts' => (int) $referral->contact_attempts,
                'last_contact_channel' => $referral->last_contact_channel,
                'contact_note' => $referral->contact_note,
                'psychologist' => [
                    'id'                  => $referral->user?->id,
                    'name'                => $referral->user?->name,
                    'email'               => $referral->user?->email,
                    'phone'               => $this->psychologistPhone($referral->user),
                    'activo'              => (bool) $referral->user?->activo,
                    'subscription_status' => optional($referral->user?->subscription)->stripe_status,
                    'trial_ends_at'       => optional($referral->user?->subscription?->trial_ends_at)->toDateString(),
                    'has_lifetime_access' => (bool) $referral->user?->has_lifetime_access,
                ],
            ];
        })->values();

        // Historial de comisiones (más recientes primero)
        $commissionItems = $vendedor->commissionItems
            ->sortByDesc('eligible_at')
            ->values()
            ->map(function ($item) {
                return [
                    'id'          => $item->id,
                    'milestone'   => $item->milestone,
                    'amount'      => (float) $item->amount,
                    'status'      => $item->status,
                    'eligible_at' => optional($item->eligible_at)->toDateString(),
                    'cut_date'    => optional($item->cut_date)->toDateString(),
                    'paid_at'     => optional($item->paid_at)->toDateTimeString(),
                ];
            });

        return Inertia::render('Vendedor/Dashboard', [
            'vendedor' => [
                'id'               => $vendedor->id,
                'nombre'           => $vendedor->nombre,
                'email'            => $vendedor->email,
                'rol'              => $vendedor->rol,
                'sales_mode'       => $vendedor->sales_mode,
                'can_register_manual_sales' => (bool) $vendedor->can_register_manual_sales,
                'imagen'           => $vendedor->imagen,
                'registration_url' => $this->registrationUrl($vendedor),
                'qr_preview_url'   => route('vendedor.qr.preview'),
                'qr_download_url'  => route('vendedor.qr'),
            ],
            'metrics' => [
                'pending_balance'  => (float) $pendingBalance,
                'paid_total'       => (float) $paidTotal,
                'next_projection'  => (float) $nextProjection,
                'referrals_count'  => $referralsCount,
                'active_count'     => $activeCount,
            'unpaid_count'     => $unpaidCount,
            'recovery_count' => $recoveryOpportunities->count(),
            'recovered_this_month' => $recoveredThisMonth,
            'recovery_commission_rate' => $recoveryCommissionRate,
            ],
            'referrals'        => $referrals,
            'commission_items' => $commissionItems,
        ]);
    }

    public function downloadQr()
    {
        /** @var \App\Models\Vendedor $vendedor */
        $vendedor = Auth::guard('vendedor_web')->user();

        return app(VendedorController::class)->download($vendedor);
    }

    public function previewQr()
    {
        /** @var \App\Models\Vendedor $vendedor */
        $vendedor = Auth::guard('vendedor_web')->user();

        return app(VendedorController::class)->qr($vendedor);
    }

    private function registrationUrl(\App\Models\Vendedor $vendedor): string
    {
        $baseUrl = rtrim(config('app.front_url_psicologo') ?: config('app.frontend_url') ?: config('app.url'), '/');

        return $baseUrl . '/register?v=' . urlencode($vendedor->qr_token);
    }

    private function psychologistPhone(?\App\Models\User $user): ?string
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
