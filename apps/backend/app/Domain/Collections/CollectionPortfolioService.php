<?php

namespace App\Domain\Collections;

use App\Domain\Tenancy\AuthorizationService;
use App\Models\CollectionTeamMember;
use App\Models\Customer;
use App\Models\CustomerCollectorAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Database\Eloquent\Builder;

final class CollectionPortfolioService
{
    public function __construct(private AuthorizationService $authorization) {}

    /** @return Builder<Customer> */
    public function customers(Tenant $tenant, TenantMembership $membership, string $capability = 'customers.view'): Builder
    {
        $decision = $this->authorization->decision($membership, $capability);
        abort_unless($decision['allowed'], 403);

        $query = Customer::forTenant($tenant);
        if ($decision['scope'] === 'all') {
            return $query;
        }
        if (! in_array($decision['scope'], ['assigned', 'team'], true)) {
            abort(403);
        }

        return $query->whereIn('customers.id', $this->customerIds($tenant, $membership, $decision['scope']));
    }

    public function canCollect(Tenant $tenant, TenantMembership $membership, Customer $customer): bool
    {
        if ($customer->tenant_id !== $tenant->id) {
            return false;
        }
        $decision = $this->authorization->decision($membership, 'payments.create');
        if (! $decision['allowed']) {
            return false;
        }
        if ($decision['scope'] === 'all') {
            return true;
        }
        if (! in_array($decision['scope'], ['assigned', 'team'], true)) {
            return false;
        }

        return $this->customerIds($tenant, $membership, $decision['scope'])->where('customer_id', $customer->id)->exists();
    }

    public function eligibleCollector(Tenant $tenant, TenantMembership $membership): bool
    {
        return $membership->tenant_id === $tenant->id
            && $membership->status === 'active'
            && $this->authorization->allows($membership, 'payments.create');
    }

    private function customerIds(Tenant $tenant, TenantMembership $membership, string $scope)
    {
        $assignments = CustomerCollectorAssignment::query()
            ->where('customer_collector_assignments.tenant_id', $tenant->id)
            ->whereNull('customer_collector_assignments.unassigned_at');

        if ($scope === 'assigned') {
            return $assignments->where('customer_collector_assignments.collector_membership_id', $membership->id)
                ->select('customer_collector_assignments.customer_id');
        }

        $supervisedTeams = CollectionTeamMember::query()
            ->select('collection_team_members.team_id')
            ->join('collection_teams', function ($join) use ($tenant): void {
                $join->on('collection_teams.id', '=', 'collection_team_members.team_id')
                    ->where('collection_teams.tenant_id', $tenant->id)
                    ->where('collection_teams.status', 'active');
            })
            ->where('collection_team_members.tenant_id', $tenant->id)
            ->where('collection_team_members.membership_id', $membership->id)
            ->where('collection_team_members.team_role', 'supervisor')
            ->whereNull('collection_team_members.left_at');

        return $assignments->whereIn('customer_collector_assignments.collector_membership_id', function ($query) use ($tenant, $supervisedTeams): void {
            $query->select('collection_team_members.membership_id')
                ->from('collection_team_members')
                ->join('tenant_memberships', function ($join) use ($tenant): void {
                    $join->on('tenant_memberships.id', '=', 'collection_team_members.membership_id')
                        ->where('tenant_memberships.tenant_id', $tenant->id)
                        ->where('tenant_memberships.status', 'active');
                })
                ->where('collection_team_members.tenant_id', $tenant->id)
                ->whereNull('collection_team_members.left_at')
                ->whereIn('collection_team_members.team_id', $supervisedTeams);
        })->select('customer_collector_assignments.customer_id');
    }
}
