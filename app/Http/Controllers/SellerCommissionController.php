<?php

namespace App\Http\Controllers;

use App\Models\SellerCommissionItem;
use App\Services\SellerCommissionService;
use App\Services\AdminVendedoresClient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SellerCommissionController extends Controller
{
    public function index(Request $request, SellerCommissionService $service)
    {
        if (config('services.admin_vendedores.integration_token')) {
            $items = collect(app(AdminVendedoresClient::class)->commissions())
                ->map(fn (array $item) => $this->transformRemoteItem($item));

            $pendingItems = $items->where('status', SellerCommissionItem::STATUS_PENDING);
            $pendingBySeller = $pendingItems
                ->groupBy('vendedor_id')
                ->map(function ($sellerItems) {
                    $first = $sellerItems->first();

                    return [
                        'vendedor_id' => $first['vendedor_id'],
                        'vendedor' => $first['vendedor'],
                        'total_pending' => (float) $sellerItems->sum('amount'),
                        'items_count' => $sellerItems->count(),
                    ];
                })
                ->values();

            return Inertia::render('SellerCommissions', [
                'cutDate' => now()->toDateString(),
                'commissionMode' => 'monthly_sales',
                'pendingBySeller' => $pendingBySeller,
                'items' => $items->values(),
                'totals' => [
                    'pending' => (float) $pendingItems->sum('amount'),
                    'paid' => (float) $items->where('status', SellerCommissionItem::STATUS_PAID)->sum('amount'),
                    'pending_items' => $pendingItems->count(),
                ],
            ]);
        }
        $cutDate = $request->filled('cut_date')
            ? Carbon::parse($request->input('cut_date'))
            : now();

        $service->generateCut($cutDate);

        $items = SellerCommissionItem::query()
            ->with(['vendedor:id,nombre,email,telefono', 'user:id,name,email', 'referral:id,status,registered_at,trial_ends_at,first_activated_at'])
            ->latest('cut_date')
            ->latest()
            ->get();

        $pendingBySeller = $items
            ->where('status', SellerCommissionItem::STATUS_PENDING)
            ->groupBy('vendedor_id')
            ->map(function ($sellerItems) {
                $first = $sellerItems->first();

                return [
                    'vendedor_id' => $first->vendedor_id,
                    'vendedor' => $first->vendedor,
                    'total_pending' => (float) $sellerItems->sum('amount'),
                    'items_count' => $sellerItems->count(),
                    'activation_count' => $sellerItems->where('milestone', 'activation')->count(),
                    'month_2_count' => $sellerItems->where('milestone', 'month_2')->count(),
                    'month_6_count' => $sellerItems->where('milestone', 'month_6')->count(),
                ];
            })
            ->values();

        return Inertia::render('SellerCommissions', [
            'cutDate' => $service->normalizeCutDate($cutDate)->toDateString(),
            'commissionMode' => 'legacy',
            'pendingBySeller' => $pendingBySeller,
            'items' => $items->map(fn (SellerCommissionItem $item) => $this->transformItem($item)),
            'totals' => [
                'pending' => (float) $items->where('status', SellerCommissionItem::STATUS_PENDING)->sum('amount'),
                'paid' => (float) $items->where('status', SellerCommissionItem::STATUS_PAID)->sum('amount'),
                'pending_items' => $items->where('status', SellerCommissionItem::STATUS_PENDING)->count(),
            ],
        ]);
    }

    public function generate(Request $request, SellerCommissionService $service)
    {
        if (config('services.admin_vendedores.integration_token')) {
            return redirect()->route('seller-commissions')->with('status', 'Las comisiones se calculan automáticamente cuando se confirma el pago de una recuperación.');
        }

        $request->validate([
            'cut_date' => ['nullable', 'date'],
        ]);

        $service->generateCut($request->filled('cut_date') ? Carbon::parse($request->input('cut_date')) : now());

        return redirect()->route('seller-commissions')->with('status', 'Corte de comisiones actualizado.');
    }

    public function markPaid(Request $request)
    {
        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['string'],
        ]);

        if (config('services.admin_vendedores.integration_token')) {
            app(AdminVendedoresClient::class)->markCommissionsPaid($validated['item_ids']);

            return redirect()->route('seller-commissions')->with('status', 'Comisiones marcadas como pagadas.');
        }

        SellerCommissionItem::query()
            ->whereIn('id', $validated['item_ids'])
            ->where('status', SellerCommissionItem::STATUS_PENDING)
            ->update([
                'status' => SellerCommissionItem::STATUS_PAID,
                'paid_at' => now(),
                'updated_at' => now(),
            ]);

        return redirect()->route('seller-commissions')->with('status', 'Comisiones marcadas como pagadas.');
    }

    protected function transformItem(SellerCommissionItem $item): array
    {
        return [
            'id' => $item->id,
            'milestone' => $item->milestone,
            'amount' => (float) $item->amount,
            'status' => $item->status,
            'eligible_at' => optional($item->eligible_at)->toDateString(),
            'cut_date' => optional($item->cut_date)->toDateString(),
            'paid_at' => optional($item->paid_at)->toDateTimeString(),
            'vendedor' => $item->vendedor,
            'psychologist' => $item->user,
            'referral' => $item->referral,
        ];
    }

    /** Maps the isolated commercial API contract to the existing Inertia page. */
    protected function transformRemoteItem(array $item): array
    {
        return [
            'id' => $item['id'],
            'vendedor_id' => $item['vendedor_id'],
            'milestone' => ($item['origen'] ?? '') === 'Venta nueva' ? 'new_sale_monthly' : 'recovery_monthly',
            'amount' => (float) ($item['monto'] ?? 0),
            'status' => ($item['estado'] ?? 'pendiente') === 'pagado'
                ? SellerCommissionItem::STATUS_PAID
                : SellerCommissionItem::STATUS_PENDING,
            'eligible_at' => $item['mes'] ?? null,
            'cut_date' => $item['mes'] ?? null,
            'paid_at' => $item['pagado_en'] ?? null,
            'vendedor' => $item['vendedor'] ?? null,
            'psychologist' => isset($item['psicologo']) ? [
                'name' => $item['psicologo']['nombre'] ?? null,
                'email' => $item['psicologo']['email'] ?? null,
                'phone' => $item['psicologo']['telefono'] ?? null,
            ] : null,
            'referral' => null,
        ];
    }
}
