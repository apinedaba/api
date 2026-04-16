<?php

namespace App\Http\Controllers;

use App\Models\SessionPackage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SessionPackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $packages = $request->user()
            ->sessionPackages()
            ->latest()
            ->get()
            ->map(fn(SessionPackage $package) => $this->transformPackage($package));

        return response()->json(['data' => $packages], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $this->validatePayload($request);

        $package = $user->sessionPackages()->create($validated);

        return response()->json([
            'message' => 'Paquete creado correctamente.',
            'data' => $this->transformPackage($package),
        ], 201);
    }

    public function update(Request $request, SessionPackage $sessionPackage): JsonResponse
    {
        abort_unless((int) $sessionPackage->user_id === (int) $request->user()->id, 403);

        $sessionPackage->update($this->validatePayload($request));

        return response()->json([
            'message' => 'Paquete actualizado correctamente.',
            'data' => $this->transformPackage($sessionPackage->fresh()),
        ], 200);
    }

    public function destroy(Request $request, SessionPackage $sessionPackage): JsonResponse
    {
        abort_unless((int) $sessionPackage->user_id === (int) $request->user()->id, 403);

        $sessionPackage->delete();

        return response()->json([
            'message' => 'Paquete eliminado correctamente.',
        ], 200);
    }

    public function publicIndex(int $professionalId): JsonResponse
    {
        $professional = User::query()
            ->publiclyVisible()
            ->findOrFail($professionalId);

        $packages = $professional
            ->activeSessionPackages()
            ->orderByDesc('is_featured')
            ->latest()
            ->get()
            ->map(fn(SessionPackage $package) => $this->transformPackage($package));

        return response()->json(['data' => $packages], 200);
    }

    protected function validatePayload(Request $request): array
    {
        if (!$request->filled('promotion_discount_type')) {
            $request->merge([
                'promotion_discount_type' => null,
                'promotion_discount_value' => null,
                'promotion_starts_at' => null,
                'promotion_ends_at' => null,
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:700'],
            'session_count' => ['required', 'integer', 'min:2', 'max:31'],
            'base_session_price' => ['required', 'numeric', 'min:1'],
            'package_session_price' => ['required', 'numeric', 'min:1'],
            'promotion_discount_type' => ['nullable', 'string', Rule::in(['percent', 'fixed'])],
            'promotion_discount_value' => ['nullable', 'numeric', 'min:0'],
            'promotion_starts_at' => ['nullable', 'date'],
            'promotion_ends_at' => ['nullable', 'date', 'after_or_equal:promotion_starts_at'],
            'currency' => ['nullable', 'string', 'max:10'],
            'formato' => ['nullable', 'string', Rule::in(['online', 'presencial', 'mixto'])],
            'tipo_sesion' => ['nullable', 'string', 'max:100'],
            'duracion' => ['nullable', 'integer', 'min:1', 'max:12'],
            'categoria' => ['nullable', 'array'],
            'categoria.*' => ['string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        $basePrice = (float) $validated['base_session_price'];
        $packagePrice = (float) $validated['package_session_price'];

        if ($packagePrice > $basePrice) {
            throw ValidationException::withMessages([
                'package_session_price' => ['El precio por sesión del paquete no puede ser mayor al precio base.'],
            ]);
        }

        if (($validated['promotion_discount_type'] ?? null) === 'percent'
            && (float) ($validated['promotion_discount_value'] ?? 0) > 100
        ) {
            throw ValidationException::withMessages([
                'promotion_discount_value' => ['El descuento porcentual no puede ser mayor a 100%.'],
            ]);
        }

        $validated['currency'] = $validated['currency'] ?? 'MXN';
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);
        $validated['promotion_discount_type'] = $validated['promotion_discount_type'] ?: null;
        $validated['promotion_discount_value'] = $validated['promotion_discount_type']
            ? (float) ($validated['promotion_discount_value'] ?? 0)
            : null;
        $validated['package_total_price'] = round(((int) $validated['session_count']) * $packagePrice, 2);

        return $validated;
    }

    protected function transformPackage(SessionPackage $package): array
    {
        $baseSessionPrice = (float) $package->base_session_price;
        $packageSessionPrice = (float) $package->package_session_price;
        $sessionCount = (int) $package->session_count;
        $baseTotalPrice = round($baseSessionPrice * $sessionCount, 2);
        $totalSavings = round($baseTotalPrice - (float) $package->package_total_price, 2);
        $savingsPercentage = $baseTotalPrice > 0
            ? round(($totalSavings / $baseTotalPrice) * 100, 2)
            : 0;

        return [
            'id' => $package->id,
            'name' => $package->name,
            'description' => $package->description,
            'session_count' => $sessionCount,
            'base_session_price' => $baseSessionPrice,
            'package_session_price' => $packageSessionPrice,
            'package_total_price' => (float) $package->package_total_price,
            'promotion_discount_type' => $package->promotion_discount_type,
            'promotion_discount_value' => $package->promotion_discount_value ? (float) $package->promotion_discount_value : null,
            'promotion_starts_at' => optional($package->promotion_starts_at)->toDateString(),
            'promotion_ends_at' => optional($package->promotion_ends_at)->toDateString(),
            'has_active_promotion' => (bool) $package->has_active_promotion,
            'promotional_session_price' => (float) $package->promotional_session_price,
            'promotional_total_price' => (float) $package->promotional_total_price,
            'base_total_price' => $baseTotalPrice,
            'total_savings' => $totalSavings,
            'savings_percentage' => $savingsPercentage,
            'currency' => $package->currency,
            'formato' => $package->formato,
            'tipo_sesion' => $package->tipo_sesion,
            'duracion' => $package->duracion,
            'categoria' => $package->categoria ?? [],
            'is_active' => (bool) $package->is_active,
            'is_featured' => (bool) $package->is_featured,
            'created_at' => optional($package->created_at)->toISOString(),
            'updated_at' => optional($package->updated_at)->toISOString(),
        ];
    }
}
