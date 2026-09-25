<?php
namespace App\Services;

use App\Models\Plan;
use Illuminate\Validation\ValidationException;

class PlanCatalogService
{
    public function resolvePaidPlan(?string $planCode, ?string $legacyPriceId = null): Plan
    {
        $query = Plan::query()->where('is_active', true)->where('code', '!=', config('plans.default'));
        $plan = $planCode
            ? (clone $query)->where('code', strtolower($planCode))->first()
            : (clone $query)->where('stripe_price_id', $legacyPriceId)->first();

        if (!$plan) {
            throw ValidationException::withMessages(['plan_code' => 'El plan solicitado no existe o no está disponible.']);
        }
        return $plan;
    }

    public function stripePriceId(Plan $plan): string
    {
        if ($plan->stripe_price_id) return $plan->stripe_price_id;
        if (!$plan->stripe_lookup_key) {
            throw ValidationException::withMessages(['plan_code' => 'Este plan aún no está configurado en Stripe.']);
        }
        $prices = \Stripe\Price::all(['lookup_keys' => [$plan->stripe_lookup_key], 'active' => true, 'limit' => 1]);
        $price = $prices->data[0] ?? null;
        if (!$price || ($price->lookup_key ?? null) !== $plan->stripe_lookup_key || empty($price->recurring)) {
            throw ValidationException::withMessages(['plan_code' => 'No se encontró un precio recurrente válido para este plan.']);
        }
        $plan->update(['stripe_price_id' => $price->id]);
        return $price->id;
    }

    public function fromStripePrice($price): ?Plan
    {
        $id = data_get($price, 'id');
        $lookupKey = data_get($price, 'lookup_key');
        return Plan::where('stripe_price_id', $id)
            ->when($lookupKey, fn ($q) => $q->orWhere('stripe_lookup_key', $lookupKey))
            ->first();
    }
}
