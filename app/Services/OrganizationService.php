<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    public function create(User $owner, array $data): Organization
    {
        return DB::transaction(function () use ($owner, $data) {
            $organization = Organization::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? $data['name']),
                'type' => $data['type'] ?? Organization::TYPE_INDIVIDUAL,
                'logo' => $data['logo'] ?? null,
                'settings' => $data['settings'] ?? null,
                'owner_id' => $owner->id,
            ]);

            OrganizationMembership::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $owner->id,
                ],
                [
                    'role' => OrganizationMembership::ROLE_OWNER,
                    'permissions' => ['*'],
                    'status' => OrganizationMembership::STATUS_ACTIVE,
                ]
            );

            return $organization->fresh(['owner', 'memberships.user']);
        });
    }

    public function inviteMember(Organization $organization, array $data): OrganizationMembership
    {
        $user = User::query()
            ->when($data['user_id'] ?? null, fn ($query, $userId) => $query->where('id', $userId))
            ->when($data['email'] ?? null, fn ($query, $email) => $query->where('email', mb_strtolower(trim($email))))
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['El usuario debe existir antes de ser invitado a una organizacion.'],
            ]);
        }

        return OrganizationMembership::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $data['role'],
                'permissions' => $data['permissions'] ?? null,
                'status' => $data['status'] ?? OrganizationMembership::STATUS_INVITED,
            ]
        )->load('user');
    }

    public function switchActiveOrganization(User $user, Organization $organization): OrganizationMembership
    {
        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', OrganizationMembership::STATUS_ACTIVE)
            ->first();

        if (!$membership) {
            throw ValidationException::withMessages([
                'organization_id' => ['No tienes una membresia activa en esta organizacion.'],
            ]);
        }

        $configurations = $user->configurations ?? [];
        $configurations['active_organization_id'] = $organization->id;
        $user->forceFill(['configurations' => $configurations])->save();

        return $membership->load('organization');
    }

    public function serializeOrganization(Organization $organization, ?User $viewer = null): array
    {
        $membership = $viewer
            ? $organization->memberships->firstWhere('user_id', $viewer->id)
            : null;

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'type' => $organization->type,
            'logo' => $organization->logo,
            'settings' => $organization->settings,
            'owner_id' => $organization->owner_id,
            'owner' => $organization->owner ? [
                'id' => $organization->owner->id,
                'name' => $organization->owner->name,
                'email' => $organization->owner->email,
            ] : null,
            'membership' => $membership ? $this->serializeMembership($membership) : null,
        ];
    }

    public function serializeMembership(OrganizationMembership $membership): array
    {
        return [
            'id' => $membership->id,
            'organization_id' => $membership->organization_id,
            'user_id' => $membership->user_id,
            'role' => $membership->role,
            'permissions' => $membership->permissions,
            'status' => $membership->status,
            'user' => $membership->user ? [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'image' => $membership->user->image,
                'activo' => $membership->user->activo,
            ] : null,
        ];
    }

    public function uniqueSlug(string $name, ?int $ignoreOrganizationId = null): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        $counter = 2;

        while (
            Organization::query()
                ->when($ignoreOrganizationId, fn ($query) => $query->where('id', '!=', $ignoreOrganizationId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
