<?php

namespace App\Policies;

use App\Models\MinderGroup;
use App\Models\User;

class MinderGroupPolicy
{
    public function access(User $user): bool
    {
        return $user->identity_verification_status === 'approved' && $user->activo;
    }

    public function view(User $user, MinderGroup $group): bool
    {
        if (! $this->access($user)) {
            return false;
        }
        return $group->members()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->access($user);
    }

    public function sendMessage(User $user, MinderGroup $group): bool
    {
        if (! $this->view($user, $group)) {
            return false;
        }
        return ! $group->isActiveBan($user->id);
    }
}
