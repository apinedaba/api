<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'type' => fake()->randomElement([Organization::TYPE_INDIVIDUAL, Organization::TYPE_CLINIC]),
            'logo' => null,
            'settings' => null,
            'owner_id' => User::factory(),
        ];
    }

    public function individual(): static
    {
        return $this->state(fn () => ['type' => Organization::TYPE_INDIVIDUAL]);
    }

    public function clinic(): static
    {
        return $this->state(fn () => ['type' => Organization::TYPE_CLINIC]);
    }
}
