<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('patient.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Comunidad Minder: canal de grupo (solo miembros)
Broadcast::channel('minder.group.{groupId}', function ($user, $groupId) {
    return \App\Models\MinderGroupMember::where('group_id', $groupId)
        ->where('user_id', $user->id)
        ->exists();
});

// Canal de soporte personal del psicólogo con el equipo Mindmeet
Broadcast::channel('minder.support.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
