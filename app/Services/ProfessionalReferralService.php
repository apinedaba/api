<?php

namespace App\Services;

use App\Models\ProfessionalReferral;
use App\Models\ProfessionalReferralCode;
use App\Models\ProfessionalReferralPointAccount;
use App\Models\ProfessionalReferralPointTransaction;
use App\Models\ProfessionalReferralReward;
use App\Models\ProfessionalReferralRewardRule;
use App\Models\ProfessionalReferralSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        $rewardMode = $this->resolveRewardModeForCode($referralCode);

        return ProfessionalReferral::create([
            'referrer_user_id' => $referralCode->user_id,
            'referred_user_id' => $referred->id,
            'professional_referral_code_id' => $referralCode->id,
            'code' => $referralCode->code,
            'status' => ProfessionalReferral::STATUS_REGISTERED,
            'reward_mode' => $rewardMode,
            'registered_at' => now(),
            'metadata' => [
                'source' => 'professional_referral_link',
                'reward_mode_locked_at' => now()->toIso8601String(),
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

        $wasQualified = $referral->status === ProfessionalReferral::STATUS_QUALIFIED;
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

        $freshReferral = $referral->fresh(['referrer.professionalReferralCode', 'referred.subscription']);
        if (!$wasQualified && $freshReferral->status === ProfessionalReferral::STATUS_QUALIFIED) {
            $this->handleQualifiedReferral($freshReferral);
        }

        return $freshReferral;
    }

    public function evaluateRewardsFor(int $referrerUserId): Collection
    {
        $qualifiedCount = ProfessionalReferral::query()
            ->where('referrer_user_id', $referrerUserId)
            ->where('status', ProfessionalReferral::STATUS_QUALIFIED)
            ->where(function ($query) {
                $query->whereNull('reward_mode')
                    ->orWhere('reward_mode', ProfessionalReferral::REWARD_MODE_FREE_MONTHS);
            })
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
        $settings = ProfessionalReferralSetting::current();
        $pointAccount = $user->professionalReferralPointAccount()->first();
        $qualifiedCount = $user->professionalReferralsMade()
            ->where('status', ProfessionalReferral::STATUS_QUALIFIED)
            ->count();
        $qualifiedForMonthsCount = $user->professionalReferralsMade()
            ->where('status', ProfessionalReferral::STATUS_QUALIFIED)
            ->where(function ($query) {
                $query->whereNull('reward_mode')
                    ->orWhere('reward_mode', ProfessionalReferral::REWARD_MODE_FREE_MONTHS);
            })
            ->count();
        $qualifiedForPointsCount = $user->professionalReferralsMade()
            ->where('status', ProfessionalReferral::STATUS_QUALIFIED)
            ->where('reward_mode', ProfessionalReferral::REWARD_MODE_MINDPOINTS)
            ->count();
        $registeredCount = $user->professionalReferralsMade()->count();

        $rules = ProfessionalReferralRewardRule::query()
            ->where('is_active', true)
            ->orderBy('required_qualified_referrals')
            ->get();

        $nextRule = $rules->firstWhere('required_qualified_referrals', '>', $qualifiedForMonthsCount);

        return [
            'code' => $code->code,
            'link' => rtrim($frontendUrl, '/') . '/register?p_ref=' . urlencode($code->code),
            'reward_preference' => $code->reward_preference ?: ProfessionalReferralCode::REWARD_FREE_MONTHS,
            'settings' => [
                'points_enabled' => (bool) $settings->points_enabled,
                'points_name' => $settings->points_name,
                'points_per_qualified_referral' => $settings->points_per_qualified_referral,
                'points_description' => $settings->points_description,
            ],
            'point_account' => [
                'balance_points' => $pointAccount?->balance_points ?? 0,
                'lifetime_earned_points' => $pointAccount?->lifetime_earned_points ?? 0,
                'lifetime_redeemed_points' => $pointAccount?->lifetime_redeemed_points ?? 0,
            ],
            'recent_point_transactions' => $user->professionalReferralPointTransactions()
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (ProfessionalReferralPointTransaction $transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'points' => $transaction->points,
                    'description' => $transaction->description,
                    'created_at' => optional($transaction->created_at)->toDateString(),
                ])
                ->values(),
            'registered_count' => $registeredCount,
            'qualified_count' => $qualifiedCount,
            'qualified_for_months_count' => $qualifiedForMonthsCount,
            'qualified_for_points_count' => $qualifiedForPointsCount,
            'pending_count' => $user->professionalReferralsMade()
                ->whereIn('status', [ProfessionalReferral::STATUS_REGISTERED, ProfessionalReferral::STATUS_TRIALING])
                ->count(),
            'next_reward' => $nextRule ? [
                'name' => $nextRule->name,
                'required_qualified_referrals' => $nextRule->required_qualified_referrals,
                'reward_months' => $nextRule->reward_months,
                'remaining' => max(0, $nextRule->required_qualified_referrals - $qualifiedForMonthsCount),
                'progress_percent' => $nextRule->required_qualified_referrals > 0
                    ? min(100, (int) floor(($qualifiedForMonthsCount / $nextRule->required_qualified_referrals) * 100))
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
                    'reward_mode' => $referral->reward_mode ?: ProfessionalReferral::REWARD_MODE_FREE_MONTHS,
                    'registered_at' => optional($referral->registered_at)->toDateString(),
                    'qualified_at' => optional($referral->qualified_at)->toDateString(),
                ])
                ->values(),
        ];
    }

    public function updateRewardPreference(User $user, string $preference): ProfessionalReferralCode
    {
        $allowed = [
            ProfessionalReferralCode::REWARD_FREE_MONTHS,
            ProfessionalReferralCode::REWARD_MENTEPUNTOS,
        ];

        if (!in_array($preference, $allowed, true)) {
            throw ValidationException::withMessages([
                'reward_preference' => 'Selecciona una modalidad de recompensa valida.',
            ]);
        }

        $settings = ProfessionalReferralSetting::current();
        if ($preference === ProfessionalReferralCode::REWARD_MENTEPUNTOS && !$settings->points_enabled) {
            throw ValidationException::withMessages([
                'reward_preference' => 'MindPoints aun no esta activo.',
            ]);
        }

        $code = $this->ensureCodeFor($user);
        $code->update(['reward_preference' => $preference]);

        return $code->fresh();
    }

    private function handleQualifiedReferral(ProfessionalReferral $referral): void
    {
        $settings = ProfessionalReferralSetting::current();
        $rewardMode = $referral->reward_mode ?: $this->resolveRewardModeForCode($referral->referrer->professionalReferralCode);

        if ($rewardMode === ProfessionalReferral::REWARD_MODE_MINDPOINTS && $settings->points_enabled) {
            $referral->update(['reward_mode' => ProfessionalReferral::REWARD_MODE_MINDPOINTS]);
            $this->awardPointsForReferral($referral, $settings);
            return;
        }

        $referral->update(['reward_mode' => ProfessionalReferral::REWARD_MODE_FREE_MONTHS]);
        $this->evaluateRewardsFor($referral->referrer_user_id);
    }

    private function awardPointsForReferral(ProfessionalReferral $referral, ProfessionalReferralSetting $settings): void
    {
        DB::transaction(function () use ($referral, $settings) {
            $transaction = ProfessionalReferralPointTransaction::firstOrCreate(
                [
                    'professional_referral_id' => $referral->id,
                    'type' => ProfessionalReferralPointTransaction::TYPE_EARNED,
                ],
                [
                    'user_id' => $referral->referrer_user_id,
                    'points' => $settings->points_per_qualified_referral,
                    'status' => 'posted',
                    'description' => 'Referido calificado: ' . (optional($referral->referred)->name ?: optional($referral->referred)->email ?: 'psicologo referido'),
                    'metadata' => [
                        'points_name' => $settings->points_name,
                        'referred_user_id' => $referral->referred_user_id,
                    ],
                ]
            );

            if (!$transaction->wasRecentlyCreated) {
                return;
            }

            $account = ProfessionalReferralPointAccount::firstOrCreate(
                ['user_id' => $referral->referrer_user_id],
                [
                    'balance_points' => 0,
                    'lifetime_earned_points' => 0,
                    'lifetime_redeemed_points' => 0,
                ]
            );

            $account->increment('balance_points', $transaction->points);
            $account->increment('lifetime_earned_points', $transaction->points);
        });
    }

    private function resolveRewardModeForCode(?ProfessionalReferralCode $referralCode): string
    {
        $settings = ProfessionalReferralSetting::current();
        $preference = $referralCode?->reward_preference ?: ProfessionalReferralCode::REWARD_FREE_MONTHS;

        if ($preference === ProfessionalReferralCode::REWARD_MENTEPUNTOS && $settings->points_enabled) {
            return ProfessionalReferral::REWARD_MODE_MINDPOINTS;
        }

        return ProfessionalReferral::REWARD_MODE_FREE_MONTHS;
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
