<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
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
                'psychologist' => [
                    'id'                  => $referral->user?->id,
                    'name'                => $referral->user?->name,
                    'email'               => $referral->user?->email,
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
                'id'     => $vendedor->id,
                'nombre' => $vendedor->nombre,
                'email'  => $vendedor->email,
                'rol'    => $vendedor->rol,
                'imagen' => $vendedor->imagen,
            ],
            'metrics' => [
                'pending_balance'  => (float) $pendingBalance,
                'paid_total'       => (float) $paidTotal,
                'next_projection'  => (float) $nextProjection,
                'referrals_count'  => $referralsCount,
                'active_count'     => $activeCount,
                'unpaid_count'     => $unpaidCount,
            ],
            'referrals'        => $referrals,
            'commission_items' => $commissionItems,
        ]);
    }
}
