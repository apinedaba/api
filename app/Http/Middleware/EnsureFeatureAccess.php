<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureFeatureAccess
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        $user = $request->user();
        if (!$user || !$user->canUseFeature($feature)) {
            return response()->json([
                'message' => 'Esta función no está incluida en tu plan.',
                'code' => 'feature_not_available',
                'feature' => $feature,
                'plan' => $user ? app(\App\Services\PlanAccessService::class)->currentPlan($user)->code : null,
            ], 403);
        }
        return $next($request);
    }
}
