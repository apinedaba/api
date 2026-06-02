<?php

namespace Database\Factories;

use App\Models\MarketingPackage;
use App\Enums\MarketingPackageType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * MarketingPackageFactory
 * 
 * Genera datos de prueba realistas para marketing packages
 * 
 * Uso:
 *   MarketingPackage::factory()->create();
 *   MarketingPackage::factory(5)->create();
 *   MarketingPackage::factory()->individual()->create();
 *   MarketingPackage::factory()->group()->create();
 */
class MarketingPackageFactory extends Factory
{
    protected $model = MarketingPackage::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->unique()->slug(3),
            'description' => $this->faker->sentence(10),
            'type' => $this->faker->randomElement([
                MarketingPackageType::Individual,
                MarketingPackageType::Group,
            ]),
            'price' => $this->faker->numberBetween(1000, 5000),
            'max_slots' => $this->faker->numberBetween(1, 50),
            'stripe_product_id' => 'prod_test_' . $this->faker->unique()->numberBetween(100000, 999999),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Crear un paquete individual
     */
    public function individual(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => MarketingPackageType::Individual,
                'max_slots' => 1,
                'price' => 1500,
                'name' => 'Solo Psicólogo - ' . $this->faker->word(),
            ];
        });
    }

    /**
     * Crear un paquete grupal
     */
    public function group(int $slots = 10): static
    {
        return $this->state(function (array $attributes) use ($slots) {
            return [
                'type' => MarketingPackageType::Group,
                'max_slots' => $slots,
                'price' => 2000 + ($slots * 100),
                'name' => "CombiMindMeet ({$slots} psicólogos)",
            ];
        });
    }

    /**
     * Crear paquete inactivo
     */
    public function inactive(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
            ];
        });
    }

    /**
     * Crear paquete premium
     */
    public function premium(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'price' => $this->faker->numberBetween(5000, 10000),
                'name' => 'Premium - ' . $this->faker->word(),
            ];
        });
    }
}
