<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

class OrganizationPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->activeMembership($user, $organization) !== null;
    }

    public function switch(User $user, Organization $organization): bool
    {
        return $this->view($user, $organization);
    }

    public function viewMembers(User $user, Organization $organization): bool
    {
        return $this->view($user, $organization);
    }

    public function inviteMember(User $user, Organization $organization): bool
    {
        $membership = $this->activeMembership($user, $organization);

        return $membership?->canManageMembers() ?? false;
    }

    private function activeMembership(User $user, Organization $organization): ?OrganizationMembership
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', OrganizationMembership::STATUS_ACTIVE)
            ->first();
    }
}
