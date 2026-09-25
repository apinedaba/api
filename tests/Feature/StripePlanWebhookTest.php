<?php
namespace Tests\Feature;

use App\Jobs\HandleStripeEventJob;
use App\Models\Plan;
use App\Models\StripeWebhookEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StripeSubscriptionService;
use Database\Seeders\PlanFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripePlanWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanFeatureSeeder::class);
    }

    public function test_webhook_job_is_idempotent(): void
    {
        $event = (object) ['id' => 'evt_once', 'type' => 'unhandled.test'];
        StripeWebhookEvent::create(['event_id' => $event->id, 'type' => $event->type, 'status' => 'pending']);
        $job = new HandleStripeEventJob($event);
        $job->handle();
        $job->handle();
        $this->assertSame('processed', StripeWebhookEvent::where('event_id', 'evt_once')->value('status'));
    }

    public function test_subscription_update_maps_price_and_definitive_cancellation_returns_user_to_free(): void
    {
        $user = User::factory()->create();
        $pro = Plan::where('code', 'pro')->firstOrFail();
        $pro->update(['stripe_price_id' => 'price_pro_webhook']);
        Subscription::create(['user_id' => $user->id, 'stripe_id' => 'sub_webhook', 'stripe_status' => 'pending']);
        $stripeSubscription = json_decode(json_encode([
            'id' => 'sub_webhook', 'status' => 'trialing', 'trial_end' => null,
            'cancel_at_period_end' => false, 'items' => ['data' => [['price' => ['id' => 'price_pro_webhook']]]],
        ]));

        $service = app(StripeSubscriptionService::class);
        $service->updateSubscription($stripeSubscription);
        $this->assertSame($pro->id, $user->subscription()->value('plan_id'));
        $this->assertTrue($user->fresh()->canUseFeature('realtime_schedule'));

        $service->cancelSubscription($stripeSubscription);
        $this->assertSame('free', app(\App\Services\PlanAccessService::class)->currentPlan($user->fresh())->code);
    }
}
