<?php

namespace App\Services;

use App\Models\MinderGroup;
use App\Models\MinderGroupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MinderDirectMessageService
{
    public function findOrCreate(User $sender, User $recipient): array
    {
        return DB::transaction(function () use ($sender, $recipient) {
            $existing = MinderGroup::where('is_dm', true)
                ->whereHas('groupMembers', fn($query) => $query->where('user_id', $sender->id))
                ->whereHas('groupMembers', fn($query) => $query->where('user_id', $recipient->id))
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return ['group' => $existing, 'created' => false];
            }

            $firstId = min($sender->id, $recipient->id);
            $secondId = max($sender->id, $recipient->id);

            $group = MinderGroup::create([
                'name' => "dm:{$firstId}:{$secondId}",
                'slug' => "dm-{$firstId}-{$secondId}-" . Str::random(5),
                'type' => 'private',
                'is_dm' => true,
                'max_members' => 2,
                'created_by' => $sender->id,
                'is_active' => true,
            ]);

            MinderGroupMember::create(['group_id' => $group->id, 'user_id' => $sender->id, 'role' => 'member']);
            MinderGroupMember::create(['group_id' => $group->id, 'user_id' => $recipient->id, 'role' => 'member']);

            return ['group' => $group, 'created' => true];
        });
    }
}
