<?php

namespace App\Http\Controllers;

use App\Models\DiscountCoupon;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DiscountCouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coupons = $request->user()
            ->discountCoupons()
            ->latest()
            ->get()
            ->map(fn (DiscountCoupon $coupon) => $this->transformCoupon($coupon));

        return response()->json(['data' => $coupons], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $coupon = $request->user()
            ->discountCoupons()
            ->create($this->validatePayload($request, $request->user()->id));

        return response()->json([
            'message' => 'Cupon creado correctamente.',
            'data' => $this->transformCoupon($coupon),
        ], 201);
    }

    public function update(Request $request, DiscountCoupon $coupon): JsonResponse
    {
        abort_unless((int) $coupon->user_id === (int) $request->user()->id, 403);

        $coupon->update($this->validatePayload($request, $request->user()->id, $coupon));

        return response()->json([
            'message' => 'Cupon actualizado correctamente.',
            'data' => $this->transformCoupon($coupon->fresh()),
        ], 200);
    }

    public function destroy(Request $request, DiscountCoupon $coupon): JsonResponse
    {
        abort_unless((int) $coupon->user_id === (int) $request->user()->id, 403);

        $coupon->delete();

        return response()->json(['message' => 'Cupon eliminado correctamente.'], 200);
    }

    public function adminIndex(): Response
    {
        $coupons = DiscountCoupon::with('user:id,name,email,image')
            ->latest()
            ->get()
            ->map(fn (DiscountCoupon $coupon) => $this->transformCoupon($coupon, true));

        $psychologists = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Coupons', [
            'coupons' => $coupons,
            'psychologists' => $psychologists,
            'status' => session('status'),
        ]);
    }

    public function adminStore(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $validated = $this->validatePayload($request, (int) $request->input('user_id'));
        DiscountCoupon::create($validated);

        return redirect()->route('coupons')->with('status', 'Cupon creado correctamente.');
    }

    public function adminUpdate(Request $request, DiscountCoupon $coupon)
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $validated = $this->validatePayload($request, (int) $request->input('user_id'), $coupon);
        $coupon->update($validated);

        return redirect()->route('coupons')->with('status', 'Cupon actualizado correctamente.');
    }

    public function adminDestroy(DiscountCoupon $coupon)
    {
        $coupon->delete();

        return redirect()->route('coupons')->with('status', 'Cupon eliminado correctamente.');
    }

    protected function validatePayload(Request $request, int $userId, ?DiscountCoupon $coupon = null): array
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('discount_coupons', 'code')
                    ->where(fn ($query) => $query->where('user_id', $userId))
                    ->ignore($coupon?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:700'],
            'discount_type' => ['required', 'string', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'applies_to' => ['required', 'string', Rule::in(['all', 'sessions', 'packages'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['user_id'] = $userId;
        $validated['code'] = strtoupper(trim($validated['code']));

        if ($validated['discount_type'] === 'percent' && (float) $validated['discount_value'] > 100) {
            throw ValidationException::withMessages([
                'discount_value' => ['El descuento porcentual no puede ser mayor a 100%.'],
            ]);
        }

        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['max_redemptions'] = $validated['max_redemptions'] ?? null;

        return $validated;
    }

    protected function transformCoupon(DiscountCoupon $coupon, bool $includeUser = false): array
    {
        $data = [
            'id' => $coupon->id,
            'user_id' => $coupon->user_id,
            'code' => $coupon->code,
            'name' => $coupon->name,
            'description' => $coupon->description,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'applies_to' => $coupon->applies_to,
            'starts_at' => optional($coupon->starts_at)->toDateString(),
            'ends_at' => optional($coupon->ends_at)->toDateString(),
            'max_redemptions' => $coupon->max_redemptions,
            'redeemed_count' => (int) $coupon->redeemed_count,
            'is_active' => (bool) $coupon->is_active,
            'is_currently_available' => (bool) $coupon->is_currently_available,
            'created_at' => optional($coupon->created_at)->toISOString(),
        ];

        if ($includeUser) {
            $data['user'] = $coupon->user;
        }

        return $data;
    }
}
