<?php
namespace App\Services;

use App\Models\Plan;
use App\Models\User;

class PlanAccessService
{
    public function currentPlan(User $user): Plan
    {
        if ($user->has_lifetime_access || optional($user->subscription)->stripe_status === 'clinic_managed') {
            return $this->plan(config('plans.legacy_paid_fallback'));
        }

        $subscription = $user->relationLoaded('subscription') ? $user->subscription : $user->subscription()->first();
        if ($subscription && in_array($subscription->stripe_status, ['active', 'trialing'], true)) {
            if ($subscription->plan_id && ($plan = Plan::find($subscription->plan_id))) return $plan;
            if ($subscription->stripe_plan && ($plan = Plan::where('stripe_price_id', $subscription->stripe_plan)->first())) return $plan;
            return $this->plan(config('plans.legacy_paid_fallback'));
        }

        return $this->plan(config('plans.default'));
    }

    public function canUseFeature(User $user, string $code): bool
    {
        if (!$this->hasActivatedPlan($user)) return false;
        $feature = $this->entitlement($user, $code);
        return (bool) ($feature?->pivot?->enabled ?? false);
    }

    public function featureLimit(User $user, string $code): ?int
    {
        if (!$this->hasActivatedPlan($user)) return 0;
        $feature = $this->entitlement($user, $code);
        return $feature?->pivot?->enabled ? $feature->pivot->usage_limit : 0;
    }

    public function featureUsage(User $user, string $code): int
    {
        return match ($code) {
            'patients' => $user->patientUsers()->whereNull('archived_at')->count(),
            default => 0,
        };
    }

    public function canUseMore(User $user, string $code): bool
    {
        if (!$this->canUseFeature($user, $code)) return false;
        $limit = $this->featureLimit($user, $code);
        return $limit === null || $this->featureUsage($user, $code) < $limit;
    }

    private function hasActivatedPlan(User $user): bool
    {
        if ($user->has_lifetime_access) return true;
        $subscription = $user->relationLoaded('subscription') ? $user->subscription : $user->subscription()->first();
        return $subscription && in_array($subscription->stripe_status, ['free', 'active', 'trialing', 'clinic_managed'], true);
    }

    public function summary(User $user): array
    {
        $plan = $this->currentPlan($user)->loadMissing('features');
        $isActivated = $this->hasActivatedPlan($user);
        $features = collect(config('plans.features'))->mapWithKeys(function ($name, $code) use ($user, $plan, $isActivated) {
            $feature = $plan->features->firstWhere('code', $code);
            $enabled = $isActivated && (bool) ($feature?->pivot?->enabled ?? false);
            $limit = $enabled ? $feature?->pivot?->usage_limit : 0;
            $used = $limit !== null ? $this->featureUsage($user, $code) : null;
            return [$code => compact('enabled', 'limit', 'used') + [
                'remaining' => $limit === null ? null : max(0, $limit - $used),
            ]];
        });

        return [
            'plan' => ['code' => $plan->code, 'name' => $plan->name],
            'subscription' => [
                'status' => optional($user->subscription)->stripe_status ?: 'free',
                'ends_at' => optional($user->subscription)->ends_at?->toIso8601String(),
            ],
            'features' => $features->all(),
        ];
    }

    private function entitlement(User $user, string $code)
    {
        return $this->currentPlan($user)->features()->where('code', $code)->first();
    }

    private function plan(string $code): Plan
    {
        return Plan::where('code', $code)->firstOrFail();
    }
}
