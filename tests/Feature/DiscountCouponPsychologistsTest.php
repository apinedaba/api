<?php

namespace Tests\Feature;

use App\Models\DiscountCoupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
