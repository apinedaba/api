<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use App\Mail\CampaignActivatedMail;
use App\Mail\CampaignFinishedMail;
use App\Mail\CampaignPaymentCompletedMail;
use App\Models\Administrator;
use App\Models\User;
use App\Models\MarketingPackage;
use App\Models\CampaignRequest;
use App\Models\GroupCampaign;
use App\Enums\MarketingPackageType;
use App\Enums\CampaignRequestStatus;
use App\Enums\GroupCampaignStatus;
use App\Services\FakeStripeService;
use App\Services\MarketingPaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

/**
 * MarketingFlowTest
 * 
 * Tests del flujo completo de marketing sin Stripe
 * 
 * Ejecutar:
 *   php artisan test tests/Feature/Marketing/MarketingFlowTest.php
 *   php artisan test tests/Feature/Marketing/MarketingFlowTest.php --filter=test_psicologist_can_view_packages
 */
class MarketingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $psicologist;
    protected User $psicologist2;

    public function setUp(): void
    {
        parent::setUp();

        // Setup
        $this->seed('MarketingPackageSeeder');
        $this->psicologist = User::factory()->create();
        $this->psicologist2 = User::factory()->create();

        FakeStripeService::reset();
    }

    private function actingAsPsychologist(User $user): self
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function validCampaignPayload(int $packageId, array $overrides = []): array
    {
        return array_replace_recursive([
            'marketing_package_id' => $packageId,
            'target_audience' => [
                'interests' => ['ansiedad'],
                'specialty_focus' => 'Psicología Clínica',
                'age_range' => '25-65',
                'gender' => 'todos',
            ],
            'locations' => ['CDMX'],
        ], $overrides);
    }

    /** ========== TESTS: GET /api/user/marketing/packages ========== */

    /**
     * Test: Psicólogo puede ver paquetes de marketing disponibles
     */
    public function test_psicologist_can_view_packages(): void
    {
        $response = $this->actingAsPsychologist($this->psicologist)
            ->getJson('/api/user/marketing/packages');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');

        // Verificar estructura
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'type',
                    'price',
                    'max_slots',
                    'current_slots',
                ]
            ]
        ]);
    }

    /**
     * Test: Solo se muestran paquetes activos
     */
    public function test_active_packages_only(): void
    {
        $response = $this->actingAsPsychologist($this->psicologist)
            ->getJson('/api/user/marketing/packages');

        $this->assertCount(5, $response->json('data'));
        $this->assertFalse(collect($response->json('data'))->contains('slug', 'paquete-descontinuado'));
    }

    public function test_packages_lock_only_the_same_individual_package(): void
    {
        $package = MarketingPackage::where('type', MarketingPackageType::Individual)
            ->where('is_active', true)
            ->first();

        CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'status' => CampaignRequestStatus::Paid->value,
            'target_audience' => [],
            'locations' => [],
        ]);

        $response = $this->actingAsPsychologist($this->psicologist)
            ->getJson('/api/user/marketing/packages');

        $response->assertOk();

        $packages = collect($response->json('data'));
        $sameIndividual = $packages->firstWhere('id', $package->id);
        $groupPackage = $packages->firstWhere('type', MarketingPackageType::Group->value);

        $this->assertFalse($sameIndividual['can_purchase']);
        $this->assertSame('Ya tienes este paquete individual vigente.', $sameIndividual['purchase_block_reason']);
        $this->assertTrue($groupPackage['can_purchase']);
        $this->assertNull($groupPackage['purchase_block_reason']);
    }

    /** ========== TESTS: POST /api/user/marketing/campaign-requests (Individual) ========== */

    /**
     * Test: Crear campaña individual
     */
    public function test_create_individual_campaign(): void
    {
        $package = MarketingPackage::where('type', MarketingPackageType::Individual)
            ->where('is_active', true)
            ->first();

        $campaignData = $this->validCampaignPayload($package->id, [
            'target_audience' => [
                'interests' => ['ansiedad', 'depresión'],
            ],
            'locations' => ['CDMX', 'Guadalajara'],
        ]);

        $response = $this->actingAsPsychologist($this->psicologist)
            ->postJson('/api/user/marketing/campaign-requests', $campaignData);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', CampaignRequestStatus::PendingPayment->value)
            ->assertJsonPath('data.user_id', $this->psicologist->id)
            ->assertJsonPath('data.marketing_package_id', $package->id);

        // Verificar que se guardó en BD
        $this->assertDatabaseHas('campaign_requests', [
            'user_id' => $this->psicologist->id,
            'status' => CampaignRequestStatus::PendingPayment->value,
        ]);
    }

    public function test_psicologist_cannot_create_same_individual_package_while_active(): void
    {
        $package = MarketingPackage::where('type', MarketingPackageType::Individual)
            ->where('is_active', true)
            ->first();

        CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'status' => CampaignRequestStatus::Active->value,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
            'target_audience' => [],
            'locations' => [],
        ]);

        $response = $this->actingAsPsychologist($this->psicologist)
            ->postJson('/api/user/marketing/campaign-requests', $this->validCampaignPayload($package->id));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('campaign');
    }

    public function test_psicologist_can_join_group_campaign_while_has_active_individual_campaign(): void
    {
        $individualPackage = MarketingPackage::where('type', MarketingPackageType::Individual)
            ->where('is_active', true)
            ->first();

        $groupPackage = MarketingPackage::where('type', MarketingPackageType::Group)
            ->where('is_active', true)
            ->first();

        CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $individualPackage->id,
            'status' => CampaignRequestStatus::Active->value,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
            'target_audience' => [],
            'locations' => [],
        ]);

        $response = $this->actingAsPsychologist($this->psicologist)
            ->postJson('/api/user/marketing/campaign-requests', $this->validCampaignPayload($groupPackage->id));

        $response->assertCreated()
            ->assertJsonPath('data.marketing_package_id', $groupPackage->id);
    }

    public function test_psicologist_cannot_request_same_group_package_twice(): void
    {
        $groupPackage = MarketingPackage::where('type', MarketingPackageType::Group)
            ->where('is_active', true)
            ->first();

        $this->actingAsPsychologist($this->psicologist)
            ->postJson('/api/user/marketing/campaign-requests', $this->validCampaignPayload($groupPackage->id))
            ->assertCreated();

        $response = $this->actingAsPsychologist($this->psicologist)
            ->postJson('/api/user/marketing/campaign-requests', $this->validCampaignPayload($groupPackage->id, [
                'locations' => ['Monterrey'],
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('campaign');

        $packagesResponse = $this->actingAsPsychologist($this->psicologist)
            ->getJson('/api/user/marketing/packages');

        $packagePayload = collect($packagesResponse->json('data'))->firstWhere('id', $groupPackage->id);

        $this->assertFalse($packagePayload['can_purchase']);
        $this->assertSame('Ya solicitaste este paquete CombiMindMeet.', $packagePayload['purchase_block_reason']);
    }

    /**
     * Test: Crear campaña grupal (crea GroupCampaign si no existe)
     */
    public function test_create_group_campaign_creates_group(): void
    {
        $package = MarketingPackage::where('type', MarketingPackageType::Group)
            ->where('max_slots', 10)
            ->first();

        // Primera solicitud
        $response1 = $this->actingAsPsychologist($this->psicologist)
            ->postJson('/api/user/marketing/campaign-requests', $this->validCampaignPayload($package->id));

        $response1->assertStatus(201);
        $campaign1Id = $response1->json('data.id');
        $campaign1 = CampaignRequest::find($campaign1Id);

        // Verificar que tiene group_campaign_id
        $this->assertNotNull($campaign1->group_campaign_id);
        $this->assertEquals(GroupCampaignStatus::Recruiting->value, $campaign1->groupCampaign->status->value);
    }

    /**
     * Test: Múltiples psicólogos usan el mismo GroupCampaign
     */
    public function test_multiple_psychologists_join_same_group(): void
    {
        $package = MarketingPackage::where('type', MarketingPackageType::Group)
            ->where('max_slots', 5)
            ->first();

        // Psicólogo 1 crea campaña
        $response1 = $this->actingAsPsychologist($this->psicologist)
            ->postJson('/api/user/marketing/campaign-requests', $this->validCampaignPayload($package->id));

        $campaign1 = CampaignRequest::find($response1->json('data.id'));
        $groupId = $campaign1->group_campaign_id;

        // Psicólogo 2 crea campaña
        $response2 = $this->actingAsPsychologist($this->psicologist2)
            ->postJson('/api/user/marketing/campaign-requests', $this->validCampaignPayload($package->id, [
                'target_audience' => ['interests' => ['depresión']],
                'locations' => ['Guadalajara'],
            ]));

        $campaign2 = CampaignRequest::find($response2->json('data.id'));

        // Ambas deben estar en el mismo grupo
        $this->assertEquals($groupId, $campaign2->group_campaign_id);

        // Pero ser campaña requests diferentes
        $this->assertNotEquals($campaign1->id, $campaign2->id);
    }

    /** ========== TESTS: POST /api/user/marketing/campaign-requests/{id}/checkout ========== */

    /**
     * Test: Crear sesión de checkout
     */
    public function test_create_checkout_session(): void
    {
        $package = MarketingPackage::where('type', MarketingPackageType::Individual)
            ->where('is_active', true)
            ->first();

        $campaign = CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'status' => CampaignRequestStatus::PendingPayment->value,
            'target_audience' => [],
            'locations' => [],
        ]);

        $response = $this->actingAsPsychologist($this->psicologist)
            ->postJson("/api/user/marketing/campaign-requests/{$campaign->id}/checkout");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'url',
                'session_id',
            ]);

        $sessionId = $response->json('session_id');
        $this->assertNotEmpty($sessionId);
    }

    /** ========== TESTS: WEBHOOK HANDLER ========== */

    /**
     * Test: Webhook marca campaña como "Paid"
     */
    public function test_webhook_marks_campaign_as_paid(): void
    {
        Mail::fake();

        $package = MarketingPackage::where('type', MarketingPackageType::Individual)
            ->where('is_active', true)
            ->first();

        $campaign = CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'status' => CampaignRequestStatus::PendingPayment->value,
            'target_audience' => [],
            'locations' => [],
        ]);

        // Simular webhook
        $sessionId = 'cs_test_' . uniqid();
        $webhookData = (object) [
            'id' => $sessionId,
            'customer' => 'cus_test_123',
            'metadata' => [
                'campaign_request_id' => $campaign->id,
            ],
        ];

        $service = app(MarketingPaymentService::class);
        $service->handleCheckoutCompleted($webhookData);

        // Verificar estado actualizado
        $campaign->refresh();
        $this->assertEquals(CampaignRequestStatus::Paid->value, $campaign->status->value);
        Mail::assertSent(CampaignPaymentCompletedMail::class);
    }

    /**
     * Test: Webhook incrementa slots en campaña grupal
     */
    public function test_webhook_increments_group_slots(): void
    {
        $package = MarketingPackage::where('type', MarketingPackageType::Group)
            ->first();

        $groupCampaign = GroupCampaign::create([
            'marketing_package_id' => $package->id,
            'current_slots' => 0,
            'status' => GroupCampaignStatus::Recruiting->value,
        ]);

        $campaign = CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'group_campaign_id' => $groupCampaign->id,
            'status' => CampaignRequestStatus::PendingPayment->value,
            'target_audience' => [],
            'locations' => [],
        ]);

        // Simular webhook
        $webhookData = (object) [
            'id' => 'cs_test_' . uniqid(),
            'metadata' => [
                'campaign_request_id' => $campaign->id,
            ],
        ];

        $service = app(MarketingPaymentService::class);
        $service->handleCheckoutCompleted($webhookData);

        // Verificar incremento de slots
        $groupCampaign->refresh();
        $this->assertEquals(1, $groupCampaign->current_slots);
    }

    /**
     * Test: Webhook marca grupo como "Full" cuando se llena
     */
    public function test_webhook_marks_group_as_full(): void
    {
        // Crear paquete con solo 2 slots
        $package = MarketingPackage::create([
            'name' => 'Test Package 2 slots',
            'slug' => 'test-2-slots',
            'type' => MarketingPackageType::Group->value,
            'price' => 1000,
            'max_slots' => 2,
            'stripe_product_id' => 'prod_test_2_slots',
            'is_active' => true,
        ]);

        $groupCampaign = GroupCampaign::create([
            'marketing_package_id' => $package->id,
            'current_slots' => 1,
            'status' => GroupCampaignStatus::Recruiting->value,
        ]);

        $campaign = CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'group_campaign_id' => $groupCampaign->id,
            'status' => CampaignRequestStatus::PendingPayment->value,
            'target_audience' => [],
            'locations' => [],
        ]);

        // Procesar webhook (esto llena el último slot)
        $webhookData = (object) [
            'id' => 'cs_test_' . uniqid(),
            'metadata' => [
                'campaign_request_id' => $campaign->id,
            ],
        ];

        $service = app(MarketingPaymentService::class);
        $service->handleCheckoutCompleted($webhookData);

        // Verificar que está lleno
        $groupCampaign->refresh();
        $this->assertEquals(2, $groupCampaign->current_slots);
        $this->assertEquals(GroupCampaignStatus::Full->value, $groupCampaign->status->value);
    }

    /**
     * Test: Webhook es idempotente (procesar dos veces no causa duplicados)
     */
    public function test_webhook_idempotent(): void
    {
        $package = MarketingPackage::where('type', MarketingPackageType::Individual)
            ->where('is_active', true)
            ->first();

        $campaign = CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'status' => CampaignRequestStatus::PendingPayment->value,
            'target_audience' => [],
            'locations' => [],
        ]);

        $webhookData = (object) [
            'id' => 'cs_test_' . uniqid(),
            'metadata' => [
                'campaign_request_id' => $campaign->id,
            ],
        ];

        $service = app(MarketingPaymentService::class);

        // Procesar dos veces
        $service->handleCheckoutCompleted($webhookData);
        $service->handleCheckoutCompleted($webhookData);

        // No debe haber duplicados en la BD
        $this->assertDatabaseCount('campaign_requests', 1);

        $campaign->refresh();
        $this->assertEquals(CampaignRequestStatus::Paid->value, $campaign->status->value);
    }

    /** ========== TESTS: GET /api/user/marketing/my-campaigns ========== */

    /**
     * Test: Psicólogo ve solo sus campañas
     */
    public function test_psicologist_sees_only_own_campaigns(): void
    {
        $package = MarketingPackage::where('type', MarketingPackageType::Individual)
            ->where('is_active', true)
            ->first();

        // Crear campaña para psicólogo 1
        CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'status' => CampaignRequestStatus::PendingPayment->value,
            'target_audience' => [],
            'locations' => [],
        ]);

        // Crear campaña para psicólogo 2
        CampaignRequest::create([
            'user_id' => $this->psicologist2->id,
            'marketing_package_id' => $package->id,
            'status' => CampaignRequestStatus::PendingPayment->value,
            'target_audience' => [],
            'locations' => [],
        ]);

        // Psicólogo 1 solo ve su campaña
        $response = $this->actingAsPsychologist($this->psicologist)
            ->getJson('/api/user/marketing/my-campaigns');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertEquals(
            $this->psicologist->id,
            $response->json('data.0.user_id')
        );
    }

    public function test_psicologist_sees_campaign_run_dates(): void
    {
        $package = MarketingPackage::where('type', MarketingPackageType::Individual)
            ->where('is_active', true)
            ->first();

        $startsAt = Carbon::parse('2026-06-05 00:00:00');
        $endsAt = Carbon::parse('2026-07-05 23:59:59');

        CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'status' => CampaignRequestStatus::Active->value,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'campaign_url' => 'https://facebook.com/mindmeet/campaigns/test',
            'target_audience' => [],
            'locations' => [],
        ]);

        $response = $this->actingAsPsychologist($this->psicologist)
            ->getJson('/api/user/marketing/my-campaigns');

        $response->assertOk()
            ->assertJsonPath('data.0.status', CampaignRequestStatus::Active->value)
            ->assertJsonPath('data.0.campaign_url', 'https://facebook.com/mindmeet/campaigns/test');

        $this->assertNotNull($response->json('data.0.starts_at'));
        $this->assertNotNull($response->json('data.0.ends_at'));
    }

    public function test_admin_can_activate_paid_campaign_for_thirty_days(): void
    {
        Mail::fake();

        $admin = Administrator::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'email_verified_at' => now(),
        ]);

        $package = MarketingPackage::where('type', MarketingPackageType::Individual)
            ->where('is_active', true)
            ->first();

        $campaign = CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'status' => CampaignRequestStatus::Paid->value,
            'target_audience' => [],
            'locations' => [],
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));

        $response = $this->actingAs($admin, 'web')
            ->post(route('marketing.campaigns.activate', $campaign->id), [
                'duration_days' => 30,
                'campaign_url' => 'https://instagram.com/p/mindmeet-test',
            ]);

        Carbon::setTestNow();

        $response->assertRedirect();

        $campaign->refresh();
        $this->assertEquals(CampaignRequestStatus::Active->value, $campaign->status->value);
        $this->assertEquals('2026-06-10', $campaign->starts_at->toDateString());
        $this->assertEquals('2026-07-10', $campaign->ends_at->toDateString());
        $this->assertEquals('https://instagram.com/p/mindmeet-test', $campaign->campaign_url);

        Mail::assertSent(CampaignActivatedMail::class, function (CampaignActivatedMail $mail) {
            return $mail->hasTo($this->psicologist->email);
        });
    }

    public function test_admin_cannot_activate_group_campaign_until_slots_are_full(): void
    {
        Mail::fake();

        $admin = Administrator::create([
            'name' => 'Admin',
            'email' => 'admin-group@example.com',
            'password' => 'secret',
            'email_verified_at' => now(),
        ]);

        $package = MarketingPackage::where('type', MarketingPackageType::Group)
            ->where('is_active', true)
            ->first();

        $group = GroupCampaign::create([
            'marketing_package_id' => $package->id,
            'current_slots' => 1,
            'status' => GroupCampaignStatus::Recruiting->value,
        ]);

        $campaign = CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'group_campaign_id' => $group->id,
            'status' => CampaignRequestStatus::Paid->value,
            'target_audience' => [],
            'locations' => [],
        ]);

        $response = $this->actingAs($admin, 'web')
            ->post(route('marketing.campaigns.activate', $campaign->id), [
                'duration_days' => 30,
            ]);

        $response->assertRedirect();

        $campaign->refresh();
        $this->assertEquals(CampaignRequestStatus::Paid->value, $campaign->status->value);
        $this->assertNull($campaign->starts_at);
        Mail::assertNotSent(CampaignActivatedMail::class);
    }

    public function test_admin_can_update_campaign_brief(): void
    {
        $admin = Administrator::create([
            'name' => 'Admin',
            'email' => 'admin-brief@example.com',
            'password' => 'secret',
            'email_verified_at' => now(),
        ]);

        $package = MarketingPackage::where('type', MarketingPackageType::Individual)
            ->where('is_active', true)
            ->first();

        $campaign = CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'status' => CampaignRequestStatus::Paid->value,
            'target_audience' => ['interests' => ['ansiedad']],
            'locations' => ['CDMX'],
        ]);

        $response = $this->actingAs($admin, 'web')
            ->post(route('marketing.campaigns.brief', $campaign->id), [
                'target_audience' => [
                    'age_range' => '25-45',
                    'gender' => 'todos',
                    'specialty_focus' => 'Terapia cognitivo-conductual',
                    'interests' => ['ansiedad', 'estrés'],
                ],
                'locations' => ['CDMX', 'Monterrey'],
            ]);

        $response->assertRedirect();

        $campaign->refresh();
        $this->assertSame('25-45', $campaign->target_audience['age_range']);
        $this->assertSame(['CDMX', 'Monterrey'], $campaign->locations);
    }

    public function test_admin_can_update_campaign_link(): void
    {
        $admin = Administrator::create([
            'name' => 'Admin',
            'email' => 'admin-link@example.com',
            'password' => 'secret',
            'email_verified_at' => now(),
        ]);

        $package = MarketingPackage::where('type', MarketingPackageType::Individual)
            ->where('is_active', true)
            ->first();

        $campaign = CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'status' => CampaignRequestStatus::Paid->value,
            'target_audience' => [],
            'locations' => [],
        ]);

        $response = $this->actingAs($admin, 'web')
            ->post(route('marketing.campaigns.link', $campaign->id), [
                'campaign_url' => 'https://facebook.com/mindmeet/campaign-link',
            ]);

        $response->assertRedirect();

        $campaign->refresh();
        $this->assertEquals('https://facebook.com/mindmeet/campaign-link', $campaign->campaign_url);
    }

    public function test_admin_finish_campaign_sends_finished_email(): void
    {
        Mail::fake();

        $admin = Administrator::create([
            'name' => 'Admin',
            'email' => 'admin-finish@example.com',
            'password' => 'secret',
            'email_verified_at' => now(),
        ]);

        $package = MarketingPackage::where('type', MarketingPackageType::Individual)
            ->where('is_active', true)
            ->first();

        $campaign = CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'status' => CampaignRequestStatus::Active->value,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->addDay(),
            'target_audience' => [],
            'locations' => [],
        ]);

        $this->actingAs($admin, 'web')
            ->post(route('marketing.campaigns.finish', $campaign->id))
            ->assertRedirect();

        $campaign->refresh();
        $this->assertEquals(CampaignRequestStatus::Finished->value, $campaign->status->value);

        Mail::assertSent(CampaignFinishedMail::class, function (CampaignFinishedMail $mail) {
            return $mail->hasTo($this->psicologist->email);
        });
    }

    public function test_expire_marketing_campaigns_command_finishes_expired_campaigns(): void
    {
        Mail::fake();

        $package = MarketingPackage::where('type', MarketingPackageType::Individual)
            ->where('is_active', true)
            ->first();

        $campaign = CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'status' => CampaignRequestStatus::Active->value,
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subMinute(),
            'target_audience' => [],
            'locations' => [],
        ]);

        Artisan::call('marketing:expire-campaigns');

        $campaign->refresh();
        $this->assertEquals(CampaignRequestStatus::Finished->value, $campaign->status->value);

        Mail::assertSent(CampaignFinishedMail::class);
    }

    public function test_expire_marketing_campaigns_command_completes_group_when_all_members_expire(): void
    {
        Mail::fake();

        $package = MarketingPackage::where('type', MarketingPackageType::Group)
            ->where('is_active', true)
            ->first();

        $group = GroupCampaign::create([
            'marketing_package_id' => $package->id,
            'current_slots' => 2,
            'status' => GroupCampaignStatus::Active->value,
        ]);

        CampaignRequest::create([
            'user_id' => $this->psicologist->id,
            'marketing_package_id' => $package->id,
            'group_campaign_id' => $group->id,
            'status' => CampaignRequestStatus::Active->value,
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subMinute(),
            'target_audience' => [],
            'locations' => [],
        ]);

        CampaignRequest::create([
            'user_id' => $this->psicologist2->id,
            'marketing_package_id' => $package->id,
            'group_campaign_id' => $group->id,
            'status' => CampaignRequestStatus::Active->value,
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subMinute(),
            'target_audience' => [],
            'locations' => [],
        ]);

        Artisan::call('marketing:expire-campaigns');

        $group->refresh();
        $this->assertEquals(GroupCampaignStatus::Completed->value, $group->status->value);
        $this->assertDatabaseMissing('campaign_requests', [
            'group_campaign_id' => $group->id,
            'status' => CampaignRequestStatus::Active->value,
        ]);

        Mail::assertSent(CampaignFinishedMail::class, 2);
    }
}
