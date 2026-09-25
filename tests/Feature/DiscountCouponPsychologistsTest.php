<?php

namespace Tests\Feature;

use App\Models\DiscountCoupon;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiscountCouponPsychologistsTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_can_apply_to_multiple_psychologists(): void
    {
        $psychologists = User::factory()->count(2)->create();
        $coupon = DiscountCoupon::create([
            'user_id' => $psychologists[0]->id,
            'code' => 'EQUIPO20',
            'name' => 'Equipo',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'applies_to' => 'sessions',
            'is_active' => true,
        ]);
        $coupon->psychologists()->sync($psychologists->pluck('id'));

        foreach ($psychologists as $psychologist) {
            $this->assertTrue(
                DiscountCoupon::query()
                    ->forPsychologist($psychologist->id)
                    ->where('code', 'EQUIPO20')
                    ->currentlyAvailable()
                    ->exists()
            );
        }
    }

    public function test_patient_checkout_cart_applies_shared_coupon_on_the_server(): void
    {
        $psychologists = User::factory()->count(2)->create();
        $coupon = DiscountCoupon::create([
            'user_id' => $psychologists[0]->id,
            'code' => 'RED20',
            'name' => 'Red de profesionales',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'applies_to' => 'sessions',
            'is_active' => true,
        ]);
        $coupon->psychologists()->sync($psychologists->pluck('id'));
        $patient = Patient::query()->create([
            'name' => 'Paciente checkout',
            'email' => 'checkout-coupon@example.test',
            'password' => bcrypt('password'),
            'activo' => true,
        ]);
        Sanctum::actingAs($patient);

        $this->postJson('/api/patient/cart', [
            'user_id' => $psychologists[1]->id,
            'tipoSesion' => 'individual',
            'duracion' => '1',
            'precio' => 1000,
            'fecha' => now()->addDay()->toDateString(),
            'hora' => '10:00',
            'formato' => 'online',
            'coupon_code' => 'red20',
        ])->assertOk()
            ->assertJsonPath('coupon_code', 'RED20')
            ->assertJsonPath('coupon_discount_amount', 200)
            ->assertJsonPath('session_base_amount', 800);
    }
}
