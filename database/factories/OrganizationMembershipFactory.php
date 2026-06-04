<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrganizationMembership>
 */
class OrganizationMembershipFactory extends Factory
{
    protected $model = OrganizationMembership::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role' => OrganizationMembership::ROLE_PSYCHOLOGIST,
            'permissions' => null,
            'status' => OrganizationMembership::STATUS_ACTIVE,
        ];
    }
}
