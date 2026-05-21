<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Services\OrganizationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $organizations)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $organizations = Organization::query()
            ->with(['owner:id,name,email', 'memberships' => fn ($query) => $query->where('user_id', $user->id)])
            ->whereHas('memberships', function ($query) use ($user) {
                $query
                    ->where('user_id', $user->id)
                    ->where('status', OrganizationMembership::STATUS_ACTIVE);
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization) => $this->organizations->serializeOrganization($organization, $user))
            ->values();

        return response()->json([
            'data' => $organizations,
            'active_organization_id' => data_get($user->configurations, 'active_organization_id'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Organization::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', 'unique:organizations,slug'],
            'type' => ['required', Rule::in([Organization::TYPE_INDIVIDUAL, Organization::TYPE_CLINIC])],
            'logo' => ['nullable', 'string', 'max:2048'],
            'settings' => ['nullable', 'array'],
        ]);

        $organization = $this->organizations->create($request->user(), $data);

        return response()->json([
            'message' => 'Organizacion creada correctamente.',
            'data' => $this->organizations->serializeOrganization($organization, $request->user()),
        ], 201);
    }

    public function switch(Request $request, Organization $organization)
    {
        $this->authorize('switch', $organization);

        $membership = $this->organizations->switchActiveOrganization($request->user(), $organization);

        if ($request->hasSession()) {
            $request->session()->put('active_organization_id', $organization->id);
        }

        return response()->json([
            'message' => 'Organizacion activa actualizada.',
            'active_organization' => $this->organizations->serializeOrganization($organization->load(['owner', 'memberships']), $request->user()),
            'membership' => $this->organizations->serializeMembership($membership),
        ]);
    }

    public function inviteMember(Request $request, Organization $organization)
    {
        $this->authorize('inviteMember', $organization);

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:email'],
            'email' => ['nullable', 'email', 'exists:users,email', 'required_without:user_id'],
            'role' => ['required', Rule::in([
                OrganizationMembership::ROLE_OWNER,
                OrganizationMembership::ROLE_ADMIN,
                OrganizationMembership::ROLE_RECEPTIONIST,
                OrganizationMembership::ROLE_PSYCHOLOGIST,
                OrganizationMembership::ROLE_ASSISTANT,
            ])],
            'permissions' => ['nullable', 'array'],
            'status' => ['nullable', Rule::in([
                OrganizationMembership::STATUS_ACTIVE,
                OrganizationMembership::STATUS_INVITED,
                OrganizationMembership::STATUS_SUSPENDED,
            ])],
        ]);

        $membership = $this->organizations->inviteMember($organization, $data);

        return response()->json([
            'message' => 'Miembro invitado a la organizacion.',
            'data' => $this->organizations->serializeMembership($membership),
        ], 201);
    }

    public function members(Request $request, Organization $organization)
    {
        $this->authorize('viewMembers', $organization);

        $members = $organization->memberships()
            ->with('user:id,name,email,image,activo')
            ->orderByRaw("FIELD(role, 'owner', 'admin', 'psychologist', 'receptionist', 'assistant')")
            ->orderBy('created_at')
            ->get()
            ->map(fn (OrganizationMembership $membership) => $this->organizations->serializeMembership($membership))
            ->values();

        return response()->json(['data' => $members]);
    }
}
