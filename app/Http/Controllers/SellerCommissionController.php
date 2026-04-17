<?php

namespace App\Http\Controllers;

use App\Models\SellerCommissionItem;
use App\Services\SellerCommissionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SellerCommissionController extends Controller
{
    public function index(Request $request, SellerCommissionService $service)
    {
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
            'item_ids.*' => ['integer', 'exists:seller_commission_items,id'],
        ]);

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
}
