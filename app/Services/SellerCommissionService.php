<?php

namespace App\Services;

use App\Models\SellerCommissionItem;
use App\Models\SellerReferral;
use App\Models\User;
use App\Models\Vendedor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SellerCommissionService
{
    public const COMMISSIONS = [
        'activation' => 50,
        'month_2' => 20,
        'month_6' => 30,
    ];

    public function registerReferral(Vendedor $vendedor, User $user, ?string $code = null): SellerReferral
    {
        $trialEndsAt = now()->addDays(15);

        return SellerReferral::updateOrCreate(
            ['user_id' => $user->id],
            [
                'vendedor_id' => $vendedor->id,
                'referral_code' => $code ?: $vendedor->qr_token,
                'status' => 'trial',
                'registered_at' => now(),
                'trial_ends_at' => $trialEndsAt,
                'metadata' => [
                    'source' => 'seller_qr',
                ],
            ]
        );
    }

    public function syncReferralStatus(SellerReferral $referral, ?Carbon $cutDate = null): SellerReferral
    {
        $cutDate ??= now();
        $user = $referral->user()->with('subscription')->first();

        if (!$user) {
            $referral->update([
                'status' => 'inactive',
                'last_status_checked_at' => now(),
            ]);

            return $referral->fresh();
        }

        $isActive = $this->userHasPaidAccess($user);
        $status = $isActive ? 'active' : ($this->isInTrial($user, $referral, $cutDate) ? 'trial' : 'inactive');

        $updates = [
            'status' => $status,
            'last_status_checked_at' => now(),
        ];

        if ($isActive && !$referral->first_activated_at) {
            $updates['first_activated_at'] = $this->resolveActivationDate($user, $cutDate);
        }

        $referral->update($updates);

        return $referral->fresh();
    }

    public function syncAll(?Carbon $cutDate = null): Collection
    {
        $cutDate ??= now();

        return SellerReferral::query()
            ->with(['user.subscription', 'vendedor'])
            ->get()
            ->map(fn (SellerReferral $referral) => $this->syncReferralStatus($referral, $cutDate));
    }

    public function generateCut(?Carbon $cutDate = null): Collection
    {
        $cutDate = $this->normalizeCutDate($cutDate ?: now());
        $created = collect();

        $this->syncAll($cutDate);

        SellerReferral::query()
            ->with(['user.subscription'])
            ->whereNotNull('first_activated_at')
            ->chunkById(100, function ($referrals) use ($cutDate, $created) {
                foreach ($referrals as $referral) {
                    if (!$referral->user || !$this->userHasPaidAccess($referral->user)) {
                        continue;
                    }

                    foreach ($this->eligibleMilestones($referral, $cutDate) as $milestone => $eligibleAt) {
                        $item = SellerCommissionItem::firstOrCreate(
                            [
                                'seller_referral_id' => $referral->id,
                                'milestone' => $milestone,
                            ],
                            [
                                'vendedor_id' => $referral->vendedor_id,
                                'user_id' => $referral->user_id,
                                'amount' => self::COMMISSIONS[$milestone],
                                'status' => SellerCommissionItem::STATUS_PENDING,
                                'eligible_at' => $eligibleAt->toDateString(),
                                'cut_date' => $cutDate->toDateString(),
                            ]
                        );

                        if ($item->wasRecentlyCreated) {
                            $created->push($item);
                        }
                    }
                }
            });

        return $created;
    }

    public function normalizeCutDate(Carbon $date): Carbon
    {
        return $date->copy()->day(25)->startOfDay();
    }

    protected function eligibleMilestones(SellerReferral $referral, Carbon $cutDate): array
    {
        $activatedAt = $referral->first_activated_at instanceof Carbon
            ? $referral->first_activated_at
            : Carbon::parse($referral->first_activated_at);

        $milestones = [];

        if ($activatedAt->lte($cutDate)) {
            $milestones['activation'] = $activatedAt->copy();
        }

        if ($activatedAt->copy()->addMonthsNoOverflow(2)->lte($cutDate)) {
            $milestones['month_2'] = $activatedAt->copy()->addMonthsNoOverflow(2);
        }

        if ($activatedAt->copy()->addMonthsNoOverflow(6)->lte($cutDate)) {
            $milestones['month_6'] = $activatedAt->copy()->addMonthsNoOverflow(6);
        }

        return $milestones;
    }

    protected function userHasPaidAccess(User $user): bool
    {
        return (bool) $user->has_lifetime_access
            || in_array(optional($user->subscription)->stripe_status, ['active'], true);
    }

    protected function isInTrial(User $user, SellerReferral $referral, Carbon $date): bool
    {
        $trialEndsAt = optional($user->subscription)->trial_ends_at ?: $referral->trial_ends_at;

        return $trialEndsAt && Carbon::parse($trialEndsAt)->gte($date);
    }

    protected function resolveActivationDate(User $user, Carbon $fallback): Carbon
    {
        if ($user->has_lifetime_access) {
            return $fallback->copy();
        }

        return optional($user->subscription)->updated_at
            ? Carbon::parse($user->subscription->updated_at)
            : $fallback->copy();
    }
}
