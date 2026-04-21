<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MinderBan;
use App\Models\MinderGroup;
use App\Models\MinderGroupMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminMinderGroupController extends Controller
{
    public function index(): Response
    {
        $groups = MinderGroup::withTrashed()
            ->withCount('members', 'messages')
            ->with('creator:id,name')
            ->latest()
            ->get();

        return Inertia::render('Minder/Groups', [
            'groups' => $groups,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'type'        => 'required|in:public,private',
            'rules'       => 'nullable|string|max:1000',
            'max_members' => 'nullable|integer|min:2|max:500',
        ]);

        MinderGroup::create([
            ...$validated,
            'created_by' => auth()->id(),
            'slug'       => Str::slug($validated['name']) . '-' . Str::random(6),
        ]);

        return back()->with('success', 'Grupo creado exitosamente.');
    }

    public function update(Request $request, MinderGroup $minderGroup): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'type'        => 'sometimes|in:public,private',
            'rules'       => 'nullable|string|max:1000',
            'max_members' => 'nullable|integer|min:2|max:500',
            'is_active'   => 'sometimes|boolean',
        ]);

        $minderGroup->update($validated);

        return back()->with('success', 'Grupo actualizado.');
    }

    public function destroy(MinderGroup $minderGroup): RedirectResponse
    {
        $minderGroup->delete();

        return back()->with('success', 'Grupo desactivado.');
    }

    public function show(MinderGroup $minderGroup): Response
    {
        $minderGroup->load('creator:id,name,image');
        $members = $minderGroup->groupMembers()
            ->with('user:id,name,image,identity_verification_status')
            ->get();

        $bans = MinderBan::where('group_id', $minderGroup->id)
            ->with('user:id,name', 'admin:id,name')
            ->get();

        $psychologists = User::where('identity_verification_status', 'approved')
            ->where('activo', true)
            ->select('id', 'name', 'image')
            ->get();

        return Inertia::render('Minder/GroupDetail', [
            'group'        => $minderGroup,
            'members'      => $members,
            'bans'         => $bans,
            'psychologists' => $psychologists,
        ]);
    }

    public function addMember(Request $request, MinderGroup $minderGroup): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role'    => 'required|in:member,moderator',
        ]);

        MinderGroupMember::firstOrCreate(
            ['group_id' => $minderGroup->id, 'user_id' => $validated['user_id']],
            ['role' => $validated['role']]
        );

        return back()->with('success', 'Miembro agregado.');
    }

    public function removeMember(MinderGroup $minderGroup, User $user): RedirectResponse
    {
        MinderGroupMember::where('group_id', $minderGroup->id)
            ->where('user_id', $user->id)
            ->delete();

        return back()->with('success', 'Miembro eliminado.');
    }

    public function updateMemberRole(Request $request, MinderGroup $minderGroup, User $user): RedirectResponse
    {
        $validated = $request->validate(['role' => 'required|in:member,moderator']);

        MinderGroupMember::where('group_id', $minderGroup->id)
            ->where('user_id', $user->id)
            ->update(['role' => $validated['role']]);

        return back()->with('success', 'Rol actualizado.');
    }

    public function banMember(Request $request, MinderGroup $minderGroup, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason'     => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:now',
        ]);

        MinderBan::updateOrCreate(
            ['group_id' => $minderGroup->id, 'user_id' => $user->id],
            [
                'banned_by'  => auth()->id(),
                'reason'     => $validated['reason'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
            ]
        );

        return back()->with('success', 'Usuario baneado del grupo.');
    }

    public function unbanMember(MinderGroup $minderGroup, User $user): RedirectResponse
    {
        MinderBan::where('group_id', $minderGroup->id)->where('user_id', $user->id)->delete();

        return back()->with('success', 'Ban eliminado.');
    }
}
