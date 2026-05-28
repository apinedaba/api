<?php

namespace App\Http\Controllers\Minder;

use App\Http\Controllers\Controller;
use App\Models\MinderGroup;
use App\Models\MinderGroupMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MinderGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Grupos públicos activos + grupos privados donde el usuario es miembro
        $groups = MinderGroup::where('is_active', true)
            ->where(function ($q) use ($user) {
                $q->where('type', 'public')
                    ->orWhereHas('groupMembers', fn($q2) => $q2->where('user_id', $user->id));
            })
            ->with(['creator:id,name,image'])
            ->latest()
            ->get();

        // Carga eficiente de dm_partner: una sola query para todos los DMs
        $dmGroupIds = $groups->where('is_dm', true)->pluck('id');
        $dmPartners = [];
        if ($dmGroupIds->isNotEmpty()) {
            DB::table('minder_group_members')
                ->join('users', 'users.id', '=', 'minder_group_members.user_id')
                ->whereIn('minder_group_members.group_id', $dmGroupIds)
                ->where('minder_group_members.user_id', '!=', $user->id)
                ->select('minder_group_members.group_id', 'users.id', 'users.name', 'users.image')
                ->get()
                ->each(function ($row) use (&$dmPartners) {
                    $dmPartners[$row->group_id] = [
                        'id'     => $row->id,
                        'nombre' => $row->name,
                        'imagen' => $row->image,
                    ];
                });
        }

        $mapped = $groups->map(function (MinderGroup $g) use ($user, $dmPartners) {
            $member = $g->groupMembers()->where('user_id', $user->id)->first();

            $result = [
                'id'          => $g->id,
                'name'        => $g->name,
                'slug'        => $g->slug,
                'description' => $g->description,
                'avatar'      => $g->avatar,
                'type'        => $g->type,
                'is_dm'       => $g->is_dm,
                'created_by'  => $g->creator,
                'is_member'   => $member !== null,
                'my_role'     => $member?->role,
                'is_banned'   => $g->isActiveBan($user->id),
            ];

            if ($g->is_dm) {
                $result['dm_partner'] = $dmPartners[$g->id] ?? null;
            }

            return $result;
        });

        return response()->json(['groups' => $mapped]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', MinderGroup::class);

        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'type'        => 'required|in:public,private',
            'rules'       => 'nullable|string|max:1000',
            'max_members' => 'nullable|integer|min:2|max:500',
        ]);

        $group = MinderGroup::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        MinderGroupMember::create([
            'group_id' => $group->id,
            'user_id'  => $request->user()->id,
            'role'     => 'moderator',
        ]);

        return response()->json(['data' => $group], 201);
    }

    public function show(Request $request, MinderGroup $minderGroup): JsonResponse
    {
        $this->authorize('view', $minderGroup);

        $minderGroup->load('creator:id,name,image');
        $minderGroup->loadCount('members');

        return response()->json(['data' => $minderGroup]);
    }

    public function join(Request $request, MinderGroup $minderGroup): JsonResponse
    {
        $this->authorize('create', MinderGroup::class);

        if ($minderGroup->type === 'private') {
            return response()->json(['message' => 'Este grupo es privado.'], 403);
        }

        MinderGroupMember::firstOrCreate([
            'group_id' => $minderGroup->id,
            'user_id'  => $request->user()->id,
        ], ['role' => 'member']);

        return response()->json(['message' => 'Te uniste al grupo.']);
    }

    public function leave(Request $request, MinderGroup $minderGroup): JsonResponse
    {
        MinderGroupMember::where('group_id', $minderGroup->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Saliste del grupo.']);
    }

    /**
     * Busca psicólogos verificados activos por nombre (para iniciar un DM).
     * Excluye al propio usuario y a quienes ya tienen un DM con él.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $me = $request->user();
        $q  = trim($request->query('q', ''));

        $query = User::select('id', 'name', 'image')
            ->where('identity_verification_status', 'approved')
            ->where('activo', true)
            ->where('id', '!=', $me->id);

        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        $users = $query->orderBy('name')->limit(15)->get();

        return response()->json(['users' => $users]);
    }

    /**
     * Inicia (o recupera) un mensaje directo 1-a-1 entre dos psicólogos verificados.
     * Idempotente: si ya existe un DM entre ambos, lo devuelve sin crear uno nuevo.
     */
    public function startDirectMessage(Request $request): JsonResponse
    {
        $this->authorize('create', MinderGroup::class);

        $me = $request->user();

        $validated = $request->validate([
            'target_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::notIn([$me->id]),
            ],
        ]);

        $targetUserId = (int) $validated['target_user_id'];

        // El destinatario también debe ser psicólogo verificado y activo
        $targetUser = User::select('id', 'name', 'image', 'identity_verification_status', 'activo')
            ->findOrFail($targetUserId);

        abort_if(
            $targetUser->identity_verification_status !== 'approved' || ! $targetUser->activo,
            422,
            'El destinatario no es un psicólogo verificado activo.'
        );

        // ¿Ya existe un DM entre los dos?
        $existing = MinderGroup::where('is_dm', true)
            ->whereHas('groupMembers', fn($q) => $q->where('user_id', $me->id))
            ->whereHas('groupMembers', fn($q) => $q->where('user_id', $targetUserId))
            ->first();

        $dmPartner = [
            'id'     => $targetUser->id,
            'nombre' => $targetUser->name,
            'imagen' => $targetUser->image,
        ];

        if ($existing) {
            return response()->json([
                'data'       => array_merge($existing->toArray(), ['dm_partner' => $dmPartner]),
                'created'    => false,
            ]);
        }

        // Crear grupo DM privado
        $group = MinderGroup::create([
            'name'        => 'dm:' . min($me->id, $targetUserId) . ':' . max($me->id, $targetUserId),
            'slug'        => 'dm-' . min($me->id, $targetUserId) . '-' . max($me->id, $targetUserId) . '-' . Str::random(5),
            'type'        => 'private',
            'is_dm'       => true,
            'max_members' => 2,
            'created_by'  => $me->id,
            'is_active'   => true,
        ]);

        MinderGroupMember::create(['group_id' => $group->id, 'user_id' => $me->id,       'role' => 'member']);
        MinderGroupMember::create(['group_id' => $group->id, 'user_id' => $targetUserId, 'role' => 'member']);

        return response()->json([
            'data'    => array_merge($group->toArray(), ['dm_partner' => $dmPartner, 'is_member' => true]),
            'created' => true,
        ], 201);
    }
}
