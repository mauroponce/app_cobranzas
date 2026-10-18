<?php

namespace App\Domain\Collections;

use App\Models\CollectionTeamMember;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class CollectionAssignmentScope
{
    public function membershipIds(Tenant $tenant, TenantMembership $membership, string $scope): Builder
    {
        if (in_array($scope, ['own', 'assigned'], true)) {
            return DB::table('tenant_memberships')
                ->select('tenant_memberships.id')
                ->where('tenant_memberships.tenant_id', $tenant->id)
                ->where('tenant_memberships.id', $membership->id)
                ->where('tenant_memberships.status', 'active');
        }

        abort_unless($scope === 'team', 403);

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

        return DB::table('collection_team_members')
            ->select('collection_team_members.membership_id')
            ->join('tenant_memberships', function ($join) use ($tenant): void {
                $join->on('tenant_memberships.id', '=', 'collection_team_members.membership_id')
                    ->where('tenant_memberships.tenant_id', $tenant->id)
                    ->where('tenant_memberships.status', 'active');
            })
            ->where('collection_team_members.tenant_id', $tenant->id)
            ->whereNull('collection_team_members.left_at')
            ->whereIn('collection_team_members.team_id', $supervisedTeams);
    }
}
