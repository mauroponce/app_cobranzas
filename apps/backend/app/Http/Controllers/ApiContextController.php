<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\AuthorizationService;
use App\Domain\Tenancy\RoleMatrix;
use App\Models\Permission;
use App\Models\TenantFeature;
use App\Models\TenantMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ApiContextController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        abort_unless($request->user()->status === 'active', 403);

        return response()->json(['data' => $request->user()->only(['id', 'name', 'email'])]);
    }

    public function tenants(Request $request): JsonResponse
    {
        $memberships = $this->memberships($request)->get()->map(fn ($membership) => [
            'id' => $membership->tenant_id, 'name' => $membership->tenant->name, 'role' => $membership->role,
        ]);

        return response()->json(['data' => $memberships]);
    }

    public function select(Request $request): JsonResponse
    {
        $data = $request->validate(['tenant_id' => ['required', 'uuid']]);
        $membership = $this->memberships($request)->where('tenant_id', $data['tenant_id'])->first();
        abort_unless($membership, 403);
        $token = $request->user()->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken, 403);
        $token->forceFill(['tenant_id' => $membership->tenant_id])->save();

        return response()->json(['data' => ['tenant_id' => $membership->tenant_id, 'role' => $membership->role]]);
    }

    public function permissions(Request $request): JsonResponse
    {
        $membership = $this->activeMembership($request);

        return response()->json(['data' => ['tenant_id' => $membership->tenant_id, 'role' => $membership->role, 'permissions' => RoleMatrix::permissions($membership->role)]]);
    }

    public function capabilities(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $membership = $this->activeMembership($request);
        $permissions = Permission::query()->get()->mapWithKeys(function (Permission $permission) use ($authorization, $membership): array {
            $decision = $authorization->decision($membership, $permission->key);

            return $decision['allowed'] ? [$permission->key => $decision['scope']] : [];
        });
        $features = TenantFeature::forTenant($membership->tenant_id)->with('feature')->get()->map(fn (TenantFeature $feature) => [
            'key' => $feature->feature?->key, 'enabled' => $feature->is_enabled, 'available' => $feature->is_available,
        ])->filter(fn (array $feature) => $feature['key'] !== null)->values();

        return response()->json(['data' => [
            'user' => $request->user()->only(['id', 'name', 'email']),
            'tenant' => $membership->tenant->only(['id', 'name', 'slug', 'timezone', 'currency']),
            'role' => $membership->role,
            'features' => $features,
            'permissions' => $permissions,
            'authorization_version' => ['tenant' => $membership->tenant->authorization_version, 'membership' => $membership->authorization_version],
        ]]);
    }

    public function profile(Request $request): JsonResponse
    {
        $membership = $this->activeMembership($request);

        return response()->json(['data' => [
            'user' => $request->user()->only(['id', 'name', 'email']),
            'tenant' => $membership->tenant->only(['id', 'name', 'slug', 'timezone', 'currency']),
            'role' => $membership->role,
            'joined_at' => $membership->joined_at,
        ]]);
    }

    private function activeMembership(Request $request): TenantMembership
    {
        $token = $request->user()->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken && $token->tenant_id, 403);
        $membership = $this->memberships($request)->where('tenant_id', $token->tenant_id)->first();
        if (! $membership) {
            $token->forceFill(['tenant_id' => null])->save();
            abort(403);
        }

        return $membership;
    }

    private function memberships(Request $request): Builder
    {
        abort_unless($request->user()->status === 'active', 403);

        return TenantMembership::with('tenant')->where('user_id', $request->user()->id)->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'));
    }
}
