<?php

namespace App\Services;

use App\Models\ProfessionalReferral;
use App\Models\ProfessionalReferralCode;
use App\Models\ProfessionalReferralReward;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfessionalReferralService
{
    public const REWARD_AMOUNT = 30.00;

    public function codeFor(User $user): ProfessionalReferralCode
    {
        return ProfessionalReferralCode::firstOrCreate(
            ['user_id' => $user->id],
            ['code' => $this->uniqueCode(), 'is_active' => true]
        );
    }

    public function isValidCode(?string $code): bool
    {
        return filled($code) && ProfessionalReferralCode::where('code', trim((string) $code))->where('is_active', true)->exists();
    }

    public function attachInvitedUser(string $code, User $invited): ?ProfessionalReferral
    {
        $referralCode = ProfessionalReferralCode::where('code', $code)->where('is_active', true)->first();
        if (!$referralCode || $referralCode->user_id === $invited->id) return null;

        return ProfessionalReferral::firstOrCreate(
            ['invited_user_id' => $invited->id],
            ['inviter_user_id' => $referralCode->user_id, 'referral_code_id' => $referralCode->id, 'status' => 'registered', 'registered_at' => now()]
        );
    }

    public function creditFirstPaidSubscription(User $invited, string $invoiceId): ?ProfessionalReferralReward
    {
        return DB::transaction(function () use ($invited, $invoiceId) {
            $referral = ProfessionalReferral::where('invited_user_id', $invited->id)->lockForUpdate()->first();
            if (!$referral) return null;

            $reward = ProfessionalReferralReward::firstOrCreate(
                ['professional_referral_id' => $referral->id],
                ['user_id' => $referral->inviter_user_id, 'amount' => self::REWARD_AMOUNT, 'currency' => 'MXN', 'status' => ProfessionalReferralReward::STATUS_CREDITED, 'credited_at' => now(), 'source_reference' => $invoiceId]
            );

            if ($reward->wasRecentlyCreated) {
                $referral->update(['status' => 'rewarded', 'first_paid_at' => now(), 'first_paid_invoice_id' => $invoiceId]);
            }
            return $reward;
        });
    }

    public function summary(User $user): array
    {
        $code = $this->codeFor($user);
        $rewards = ProfessionalReferralReward::where('user_id', $user->id)->get();
        $referrals = ProfessionalReferral::with('invited:id,name,email')->where('inviter_user_id', $user->id)->latest()->get();

        return [
            'code' => $code->code,
            'reward_amount' => self::REWARD_AMOUNT,
            'balance' => (float) $rewards->where('status', ProfessionalReferralReward::STATUS_CREDITED)->sum('amount'),
            'total_earned' => (float) $rewards->whereIn('status', [ProfessionalReferralReward::STATUS_CREDITED, ProfessionalReferralReward::STATUS_PAID])->sum('amount'),
            'paid' => (float) $rewards->where('status', ProfessionalReferralReward::STATUS_PAID)->sum('amount'),
            'referrals' => $referrals->map(fn ($referral) => ['id' => $referral->id, 'name' => $referral->invited?->name, 'status' => $referral->status, 'registered_at' => $referral->registered_at, 'reward' => $referral->status === 'rewarded' ? self::REWARD_AMOUNT : 0])->values(),
        ];
    }

    private function uniqueCode(): string
    {
        do { $code = 'MM-' . Str::upper(Str::random(10)); }
        while (ProfessionalReferralCode::where('code', $code)->exists());
        return $code;
    }
}
