<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $organization = $this->resolveOrganization($request);

        if (!$organization && $this->hasExplicitOrganization($request)) {
            return response()->json([
                'message' => 'La organizacion seleccionada no existe.',
            ], 404);
        }

        if (!$organization) {
            $organization = $user->organizations()
                ->wherePivot('status', OrganizationMembership::STATUS_ACTIVE)
                ->orderBy('organizations.name')
                ->first();
        }

        if (!$organization) {
            return $next($request);
        }

        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', OrganizationMembership::STATUS_ACTIVE)
            ->first();

        if (!$membership) {
            return response()->json([
                'message' => 'No tienes acceso a la organizacion seleccionada.',
            ], 403);
        }

        $request->attributes->set('active_organization', $organization);
        $request->attributes->set('organization_membership', $membership);

        app()->instance(Organization::class, $organization);
        app()->instance(OrganizationMembership::class, $membership);

        return $next($request);
    }

    private function resolveOrganization(Request $request): ?Organization
    {
        $organizationId = $request->header('X-Organization-Id')
            ?: $request->input('organization_id')
            ?: $this->organizationIdFromToken($request)
            ?: ($request->hasSession() ? $request->session()->get('active_organization_id') : null)
            ?: data_get($request->user()?->configurations, 'active_organization_id');

        if ($organizationId && is_numeric($organizationId)) {
            return Organization::query()->find($organizationId);
        }

        $slug = $request->header('X-Organization-Slug');

        return $slug ? Organization::query()->where('slug', $slug)->first() : null;
    }

    private function hasExplicitOrganization(Request $request): bool
    {
        return filled($request->header('X-Organization-Id'))
            || filled($request->header('X-Organization-Slug'))
            || filled($request->input('organization_id'));
    }

    private function organizationIdFromToken(Request $request): ?int
    {
        $token = $request->user()?->currentAccessToken();
        $abilities = $token?->abilities ?? [];

        foreach ($abilities as $ability) {
            if (is_string($ability) && str_starts_with($ability, 'organization:')) {
                return (int) str_replace('organization:', '', $ability);
            }
        }

        return null;
    }
}
