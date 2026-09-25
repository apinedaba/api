<?php

namespace Tests\Feature;

use App\Models\ProfessionalReferralReward;
use App\Models\User;
use App\Services\ProfessionalReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalReferralRewardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_attaches_an_invited_professional_and_credits_only_once(): void
    {
        $inviter = User::factory()->create();
        $invited = User::factory()->create();
        $service = app(ProfessionalReferralService::class);
        $code = $service->codeFor($inviter);

        $referral = $service->attachInvitedUser($code->code, $invited);
        $this->assertSame($inviter->id, $referral->inviter_user_id);

        $first = $service->creditFirstPaidSubscription($invited, 'in_test_first_payment');
        $second = $service->creditFirstPaidSubscription($invited, 'in_test_duplicate_delivery');

        $this->assertSame('30.00', $first->amount);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('professional_referral_rewards', 1);
        $this->assertDatabaseHas('professional_referrals', ['invited_user_id' => $invited->id, 'status' => 'rewarded']);
        $this->assertSame(30.0, $service->summary($inviter)['balance']);
    }

    public function test_a_professional_cannot_refer_themselves(): void
    {
        $user = User::factory()->create();
        $service = app(ProfessionalReferralService::class);
        $code = $service->codeFor($user);

        $this->assertNull($service->attachInvitedUser($code->code, $user));
        $this->assertDatabaseCount('professional_referrals', 0);
        $this->assertDatabaseCount('professional_referral_rewards', 0);
    }
}
