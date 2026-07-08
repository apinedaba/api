<?php

namespace App\Services;

use App\Models\ProfessionalReferral;
use App\Models\ProfessionalReferralCode;
use App\Models\ProfessionalReferralReward;
use App\Models\ProfessionalReferralRewardRule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProfessionalReferralService
{
    public function ensureCodeFor(User $user): ProfessionalReferralCode
    {
        $existing = $user->professionalReferralCode()->first();

        if ($existing) {
            return $existing;
        }

        return ProfessionalReferralCode::create([
            'user_id' => $user->id,
            'code' => $this->generateUniqueCode($user),
            'status' => ProfessionalReferralCode::STATUS_ACTIVE,
        ]);
    }

    public function registerReferralByCode(?string $code, User $referred): ?ProfessionalReferral
    {
        $code = $this->normalizeCode($code);

        if ($code === '') {
            return null;
        }

        $referralCode = ProfessionalReferralCode::query()
            ->where('code', $code)
            ->where('status', ProfessionalReferralCode::STATUS_ACTIVE)
            ->first();

        if (!$referralCode || (int) $referralCode->user_id === (int) $referred->id) {
            return null;
        }

        if (ProfessionalReferral::where('referred_user_id', $referred->id)->exists()) {
            return null;
        }

        $referralCode->update([
            'last_used_at' => now(),
        ]);
        $referralCode->increment('clicks_count');

        return ProfessionalReferral::create([
            'referrer_user_id' => $referralCode->user_id,
            'referred_user_id' => $referred->id,
            'professional_referral_code_id' => $referralCode->id,
            'code' => $referralCode->code,
            'status' => ProfessionalReferral::STATUS_REGISTERED,
            'registered_at' => now(),
            'metadata' => [
                'source' => 'professional_referral_link',
            ],
        ]);
    }

    public function syncReferralForUser(User $user): ?ProfessionalReferral
    {
        $referral = ProfessionalReferral::query()
            ->where('referred_user_id', $user->id)
            ->with(['referred.subscription'])
            ->first();

        if (!$referral) {
            return null;
        }

        $subscriptionStatus = optional($user->subscription)->stripe_status;
        $isPaid = $user->has_lifetime_access || in_array($subscriptionStatus, ['active'], true);

        $updates = [
            'last_status_checked_at' => now(),
        ];

        if ($isPaid) {
            $updates['status'] = ProfessionalReferral::STATUS_QUALIFIED;
            $updates['first_paid_at'] = $referral->first_paid_at ?: now();
            $updates['qualified_at'] = $referral->qualified_at ?: now();
        } elseif ($subscriptionStatus === 'trialing') {
            $updates['status'] = ProfessionalReferral::STATUS_TRIALING;
        } elseif (in_array($subscriptionStatus, ['canceled', 'cancelled', 'incomplete_expired', 'unpaid'], true)) {
            $updates['status'] = ProfessionalReferral::STATUS_INACTIVE;
            $updates['cancelled_at'] = now();
        }

        $referral->update($updates);

        if ($referral->fresh()->status === ProfessionalReferral::STATUS_QUALIFIED) {
            $this->evaluateRewardsFor($referral->referrer_user_id);
        }

        return $referral->fresh();
    }

    public function evaluateRewardsFor(int $referrerUserId): Collection
    {
        $qualifiedCount = ProfessionalReferral::query()
            ->where('referrer_user_id', $referrerUserId)
            ->where('status', ProfessionalReferral::STATUS_QUALIFIED)
            ->count();

        return ProfessionalReferralRewardRule::query()
            ->where('is_active', true)
            ->where('required_qualified_referrals', '<=', $qualifiedCount)
            ->orderBy('required_qualified_referrals')
            ->get()
            ->map(function (ProfessionalReferralRewardRule $rule) use ($referrerUserId, $qualifiedCount) {
                return ProfessionalReferralReward::firstOrCreate(
                    [
                        'referrer_user_id' => $referrerUserId,
                        'milestone_key' => "qualified_{$rule->required_qualified_referrals}",
                    ],
                    [
                        'professional_referral_reward_rule_id' => $rule->id,
                        'required_qualified_referrals' => $rule->required_qualified_referrals,
                        'reward_type' => $rule->reward_type,
                        'reward_months' => $rule->reward_months,
                        'status' => ProfessionalReferralReward::STATUS_PENDING,
                        'earned_at' => now(),
                        'metadata' => [
                            'qualified_count_at_earning' => $qualifiedCount,
                        ],
                    ]
                );
            });
    }

    public function summaryFor(User $user, string $frontendUrl): array
    {
        $code = $this->ensureCodeFor($user);
        $qualifiedCount = $user->professionalReferralsMade()
            ->where('status', ProfessionalReferral::STATUS_QUALIFIED)
            ->count();
        $registeredCount = $user->professionalReferralsMade()->count();

        $rules = ProfessionalReferralRewardRule::query()
            ->where('is_active', true)
            ->orderBy('required_qualified_referrals')
            ->get();

        $nextRule = $rules->firstWhere('required_qualified_referrals', '>', $qualifiedCount);

        return [
            'code' => $code->code,
            'link' => rtrim($frontendUrl, '/') . '/register?p_ref=' . urlencode($code->code),
            'registered_count' => $registeredCount,
            'qualified_count' => $qualifiedCount,
            'pending_count' => $user->professionalReferralsMade()
                ->whereIn('status', [ProfessionalReferral::STATUS_REGISTERED, ProfessionalReferral::STATUS_TRIALING])
                ->count(),
            'next_reward' => $nextRule ? [
                'name' => $nextRule->name,
                'required_qualified_referrals' => $nextRule->required_qualified_referrals,
                'reward_months' => $nextRule->reward_months,
                'remaining' => max(0, $nextRule->required_qualified_referrals - $qualifiedCount),
                'progress_percent' => $nextRule->required_qualified_referrals > 0
                    ? min(100, (int) floor(($qualifiedCount / $nextRule->required_qualified_referrals) * 100))
                    : 100,
            ] : null,
            'earned_rewards' => $user->professionalReferralRewards()
                ->latest('earned_at')
                ->limit(5)
                ->get()
                ->map(fn (ProfessionalReferralReward $reward) => [
                    'id' => $reward->id,
                    'reward_months' => $reward->reward_months,
                    'status' => $reward->status,
                    'earned_at' => optional($reward->earned_at)->toDateString(),
                ])
                ->values(),
            'recent_referrals' => $user->professionalReferralsMade()
                ->with('referred:id,name,email')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (ProfessionalReferral $referral) => [
                    'id' => $referral->id,
                    'name' => optional($referral->referred)->name ?: optional($referral->referred)->email,
                    'status' => $referral->status,
                    'registered_at' => optional($referral->registered_at)->toDateString(),
                    'qualified_at' => optional($referral->qualified_at)->toDateString(),
                ])
                ->values(),
        ];
    }

    private function generateUniqueCode(User $user): string
    {
        $base = Str::of($user->name ?: $user->email)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '')
            ->substr(0, 8)
            ->value();

        $base = $base ?: 'MIND';

        do {
            $code = 'MIND-' . $base . '-' . Str::upper(Str::random(4));
        } while (ProfessionalReferralCode::where('code', $code)->exists());

        return $code;
    }

    private function normalizeCode(?string $code): string
    {
        return Str::upper(trim((string) $code));
    }
}
