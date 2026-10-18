<?php

namespace App\Domain\Collections;

use App\Domain\Tenancy\AuthorizationService;
use App\Models\CashSettlement;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Database\Eloquent\Builder;

final class CashSettlementScopeService
{
    public function __construct(
        private AuthorizationService $authorization,
        private CollectionAssignmentScope $assignmentScope,
    ) {}

    /** @return Builder<CashSettlement> */
    public function settlements(Tenant $tenant, TenantMembership $membership, string $capability = 'settlements.view'): Builder
    {
        $decision = $this->authorization->decision($membership, $capability);
        abort_unless($decision['allowed'], 403);

        $query = CashSettlement::forTenant($tenant);
        if ($decision['scope'] === 'all') {
            return $query;
        }

        abort_unless(in_array($decision['scope'], ['own', 'assigned', 'team'], true), 403);
        $routeMembershipIds = $this->assignmentScope->membershipIds($tenant, $membership, $decision['scope']);
        $userMembershipIds = clone $routeMembershipIds;

        return $query->where(function (Builder $query) use ($tenant, $routeMembershipIds, $userMembershipIds): void {
            $query->whereHas('route', fn (Builder $route) => $route->whereIn('collector_membership_id', $routeMembershipIds))
                ->orWhereIn('collector_id', function ($users) use ($tenant, $userMembershipIds): void {
                    $users->select('user_id')
                        ->from('tenant_memberships')
                        ->where('tenant_id', $tenant->id)
                        ->whereIn('id', $userMembershipIds);
                });
        });
    }
}
