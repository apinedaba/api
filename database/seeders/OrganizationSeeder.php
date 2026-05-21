<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();

        if (!$user) {
            return;
        }

        $organization = Organization::firstOrCreate(
            ['owner_id' => $user->id, 'type' => Organization::TYPE_INDIVIDUAL],
            [
                'name' => $user->name ?: 'MindMeet Individual',
                'slug' => $this->uniqueSlug($user->name ?: 'mindmeet-individual'),
                'settings' => ['seeded' => true],
            ]
        );

        OrganizationMembership::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            ['role' => OrganizationMembership::ROLE_OWNER, 'status' => OrganizationMembership::STATUS_ACTIVE]
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        $counter = 2;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
