<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\Audit;
use App\Domain\Tenancy\AuthorizationService;
use App\Models\CollectionTeam;
use App\Models\CollectionTeamMember;
use App\Models\CollectionZone;
use App\Models\Customer;
use App\Models\CustomerCollectionZoneAssignment;
use App\Models\TenantMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollectionStructureApiController extends Controller
{
    public function zones(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, 'collection_zones.view');

        return response()->json(['data' => CollectionZone::forTenant($request->attributes->get('activeTenant'))->orderBy('name')->get()]);
    }

    public function storeZone(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, 'collection_zones.manage');
        $zone = CollectionZone::create(['tenant_id' => $request->attributes->get('activeTenant')->id, ...$request->validate(['code' => ['required', 'string', 'max:48'], 'name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:2000']])]);
        Audit::record('collection_zone.created', $zone, $request->user(), $zone->tenant_id);

        return response()->json(['data' => $zone], 201);
    }

    public function assignZone(Request $request, Customer $customer, AuthorizationService $authorization): JsonResponse
    {
        $actor = $this->allow($request, $authorization, 'collection_zones.manage');
        $tenant = $request->attributes->get('activeTenant');
        $data = $request->validate(['zone_id' => ['required', 'uuid'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $assignment = DB::transaction(function () use ($tenant, $customer, $actor, $data): CustomerCollectionZoneAssignment {
            Customer::forTenant($tenant)->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            CollectionZone::forTenant($tenant)->whereKey($data['zone_id'])->where('status', 'active')->firstOrFail();
            CustomerCollectionZoneAssignment::forTenant($tenant)->where('customer_id', $customer->id)->whereNull('unassigned_at')->update(['unassigned_at' => now(), 'reason' => $data['reason'] ?? null]);

            return CustomerCollectionZoneAssignment::create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'zone_id' => $data['zone_id'], 'assigned_by_membership_id' => $actor->id, 'assigned_at' => now(), 'reason' => $data['reason'] ?? null]);
        });
        Audit::record('collection_zone.assigned', $assignment, $request->user(), $tenant->id);

        return response()->json(['data' => $assignment], 201);
    }

    public function teams(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, 'collection_teams.view');

        return response()->json(['data' => CollectionTeam::forTenant($request->attributes->get('activeTenant'))->with(['members' => fn ($q) => $q->whereNull('left_at')])->orderBy('name')->get()]);
    }

    public function storeTeam(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, 'collection_teams.manage');
        $team = CollectionTeam::create(['tenant_id' => $request->attributes->get('activeTenant')->id, ...$request->validate(['name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:2000']])]);
        Audit::record('collection_team.created', $team, $request->user(), $team->tenant_id);

        return response()->json(['data' => $team], 201);
    }

    public function addTeamMember(Request $request, CollectionTeam $team, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, 'collection_teams.manage_members');
        $tenant = $request->attributes->get('activeTenant');
        abort_unless($team->tenant_id === $tenant->id && $team->status === 'active', 404);
        $data = $request->validate(['membership_id' => ['required', 'uuid'], 'team_role' => ['required', 'in:supervisor,collector']]);
        $membership = TenantMembership::forTenant($tenant)->whereKey($data['membership_id'])->where('status', 'active')->firstOrFail();
        $member = CollectionTeamMember::updateOrCreate(['tenant_id' => $tenant->id, 'team_id' => $team->id, 'membership_id' => $membership->id, 'left_at' => null], ['team_role' => $data['team_role'], 'joined_at' => now()]);
        Audit::record('collection_team.member_added', $member, $request->user(), $tenant->id);

        return response()->json(['data' => $member], 201);
    }

    private function allow(Request $request, AuthorizationService $authorization, string $capability): TenantMembership
    {
        $membership = $request->attributes->get('activeMembership');
        abort_unless($authorization->allows($membership, $capability), 403);

        return $membership;
    }
}
