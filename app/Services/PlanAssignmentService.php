<?php
namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

class PlanAssignmentService
{
    public function activateFree(User $user): Subscription
    {
        $free = Plan::where('code', config('plans.default'))->firstOrFail();
        $current = $user->subscription()->first();

        if ($current && in_array($current->stripe_status, ['active', 'trialing'], true) && filled($current->stripe_id)) {
            return $current;
        }

        $subscription = Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'plan_id' => $free->id,
                'stripe_id' => null,
                'stripe_plan' => null,
                'stripe_status' => 'free',
                'trial_ends_at' => null,
                'ends_at' => null,
            ]
        );
        $user->forceFill(['plan_id' => $free->id])->saveQuietly();
        $user->setRelation('subscription', $subscription);

        return $subscription;
    }
}
