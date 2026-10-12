<?php

namespace App\Livewire;

use App\Domain\Tenancy\Audit;
use App\Domain\Tenancy\AuthorizationService;
use App\Livewire\Concerns\ResolvesTenantContext;
use App\Models\CollectionTeam;
use App\Models\CollectionTeamMember;
use App\Models\CollectionZone;
use App\Models\Customer;
use App\Models\CustomerCollectionZoneAssignment;
use App\Models\TenantMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class CollectionStructuresManager extends Component
{
    use ResolvesTenantContext;

    public string $zoneCode = '';

    public string $zoneName = '';

    public string $zoneDescription = '';

    public string $teamName = '';

    public string $teamDescription = '';

    public string $teamId = '';

    public string $membershipId = '';

    public string $teamRole = 'collector';

    public string $customerId = '';

    public string $customerZoneId = '';

    public string $editingZoneId = '';

    public string $editingTeamId = '';

    public function editZone(string $zoneId): void
    {
        Gate::authorize('collection_zones.manage');
        $zone = CollectionZone::forTenant(request()->attributes->get('activeTenant'))->whereKey($zoneId)->firstOrFail();
        $this->editingZoneId = $zone->id;
        $this->zoneCode = $zone->code;
        $this->zoneName = $zone->name;
        $this->zoneDescription = $zone->description ?? '';
    }

    public function saveZone(?string $zoneId = null): void
    {
        Gate::authorize('collection_zones.manage');
        $data = $this->validate(['zoneCode' => ['required', 'string', 'max:48'], 'zoneName' => ['required', 'string', 'max:160'], 'zoneDescription' => ['nullable', 'string', 'max:2000']]);
        $tenant = request()->attributes->get('activeTenant');
        $zoneId ??= $this->editingZoneId ?: null;
        $zone = $zoneId ? CollectionZone::forTenant($tenant)->whereKey($zoneId)->firstOrFail() : new CollectionZone(['tenant_id' => $tenant->id]);
        $zone->fill(['code' => $data['zoneCode'], 'name' => $data['zoneName'], 'description' => $data['zoneDescription'] ?: null])->save();
        Audit::record($zoneId ? 'collection_zone.updated' : 'collection_zone.created', $zone, auth()->user(), $tenant->id);
        $this->reset(['zoneCode', 'zoneName', 'zoneDescription', 'editingZoneId']);
    }

    public function toggleZone(string $zoneId): void
    {
        Gate::authorize('collection_zones.manage');
        $zone = CollectionZone::forTenant(request()->attributes->get('activeTenant'))->whereKey($zoneId)->firstOrFail();
        $zone->update(['status' => $zone->status === 'active' ? 'inactive' : 'active']);
        Audit::record('collection_zone.status_changed', $zone, auth()->user(), $zone->tenant_id);
    }

    public function editTeam(string $teamId): void
    {
        Gate::authorize('collection_teams.manage');
        $team = $this->teamsFor(request()->attributes->get('activeTenant'), 'collection_teams.manage')->whereKey($teamId)->firstOrFail();
        $this->editingTeamId = $team->id;
        $this->teamName = $team->name;
        $this->teamDescription = $team->description ?? '';
    }

    public function assignZone(): void
    {
        Gate::authorize('collection_zones.manage');
        $data = $this->validate(['customerId' => ['required', 'uuid'], 'customerZoneId' => ['required', 'uuid']]);
        $tenant = request()->attributes->get('activeTenant');
        DB::transaction(function () use ($tenant, $data): void {
            $customer = Customer::forTenant($tenant)->whereKey($data['customerId'])->lockForUpdate()->firstOrFail();
            CollectionZone::forTenant($tenant)->whereKey($data['customerZoneId'])->where('status', 'active')->firstOrFail();
            CustomerCollectionZoneAssignment::forTenant($tenant)->where('customer_id', $customer->id)->whereNull('unassigned_at')->update(['unassigned_at' => now()]);
            CustomerCollectionZoneAssignment::create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'zone_id' => $data['customerZoneId'], 'assigned_by_membership_id' => request()->attributes->get('activeMembership')->id, 'assigned_at' => now()]);
        });
        $this->reset(['customerId', 'customerZoneId']);
    }

    public function saveTeam(?string $teamId = null): void
    {
        Gate::authorize('collection_teams.manage');
        $data = $this->validate(['teamName' => ['required', 'string', 'max:160'], 'teamDescription' => ['nullable', 'string', 'max:2000']]);
        $tenant = request()->attributes->get('activeTenant');
        $teamId ??= $this->editingTeamId ?: null;
        $team = $teamId ? $this->teamsFor($tenant, 'collection_teams.manage')->whereKey($teamId)->firstOrFail() : new CollectionTeam(['tenant_id' => $tenant->id]);
        abort_unless($teamId || app(AuthorizationService::class)->decision(request()->attributes->get('activeMembership'), 'collection_teams.manage')['scope'] === 'all', 403);
        $team->fill(['name' => $data['teamName'], 'description' => $data['teamDescription'] ?: null])->save();
        Audit::record($teamId ? 'collection_team.updated' : 'collection_team.created', $team, auth()->user(), $tenant->id);
        $this->reset(['teamName', 'teamDescription', 'editingTeamId']);
    }

    public function toggleTeam(string $teamId): void
    {
        Gate::authorize('collection_teams.manage');
        $team = $this->teamsFor(request()->attributes->get('activeTenant'), 'collection_teams.manage')->whereKey($teamId)->firstOrFail();
        $team->update(['status' => $team->status === 'active' ? 'inactive' : 'active']);
        Audit::record('collection_team.status_changed', $team, auth()->user(), $team->tenant_id);
    }

    public function addMember(): void
    {
        Gate::authorize('collection_teams.manage_members');
        $data = $this->validate(['teamId' => ['required', 'uuid'], 'membershipId' => ['required', 'uuid'], 'teamRole' => ['required', 'in:supervisor,collector']]);
        $tenant = request()->attributes->get('activeTenant');
        $team = $this->teamsFor($tenant, 'collection_teams.manage_members')->whereKey($data['teamId'])->where('status', 'active')->firstOrFail();
        $membership = TenantMembership::forTenant($tenant)->whereKey($data['membershipId'])->where('status', 'active')->firstOrFail();
        $member = CollectionTeamMember::updateOrCreate(['tenant_id' => $tenant->id, 'team_id' => $team->id, 'membership_id' => $membership->id, 'left_at' => null], ['team_role' => $data['teamRole'], 'joined_at' => now()]);
        Audit::record('collection_team.member_added', $member, auth()->user(), $tenant->id);
    }

    public function removeMember(string $memberId): void
    {
        Gate::authorize('collection_teams.manage_members');
        $member = CollectionTeamMember::forTenant(request()->attributes->get('activeTenant'))->whereKey($memberId)->whereNull('left_at')->whereIn('team_id', $this->teamsFor(request()->attributes->get('activeTenant'), 'collection_teams.manage_members')->select('id'))->firstOrFail();
        $member->update(['left_at' => now()]);
        Audit::record('collection_team.member_removed', $member, auth()->user(), $member->tenant_id);
    }

    public function render(AuthorizationService $authorization): View
    {
        $tenant = request()->attributes->get('activeTenant');
        Gate::authorize('collection_zones.view');
        Gate::authorize('collection_teams.view');

        return view('livewire.collection-structures-manager', ['zones' => CollectionZone::forTenant($tenant)->with(['assignments' => fn ($query) => $query->whereNull('unassigned_at')->with('customer')])->withCount(['assignments as active_customers' => fn ($query) => $query->whereNull('unassigned_at')])->orderBy('name')->get(), 'teams' => $this->teamsFor($tenant, 'collection_teams.view')->with(['members.membership.user'])->withCount(['members as active_members' => fn ($query) => $query->whereNull('left_at')])->orderBy('name')->get(), 'memberships' => TenantMembership::forTenant($tenant)->with('user')->where('status', 'active')->get(), 'customers' => Customer::forTenant($tenant)->orderBy('last_name')->get(['id', 'first_name', 'last_name'])]);
    }

    private function teamsFor($tenant, string $capability)
    {
        $membership = request()->attributes->get('activeMembership');
        $scope = app(AuthorizationService::class)->decision($membership, $capability)['scope'];
        $query = CollectionTeam::forTenant($tenant);
        if ($scope === 'team') {
            $query->whereHas('members', fn ($members) => $members->where('membership_id', $membership->id)->whereNull('left_at'));
        }

        return $query;
    }
}
