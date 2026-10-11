<?php

namespace App\Domain\Tenancy;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MembershipService
{
    public function changeRole(Tenant $tenant, User $actor, TenantMembership $target, string $role): void
    {
        DB::transaction(function () use ($tenant, $actor, $target, $role): void {
            $lockedTenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $actorMembership = $this->activeActor($lockedTenant, $actor);
            $membership = $this->lockedTarget($tenant, $target);
            abort_unless(RoleMatrix::canManage($actorMembership->role, $membership->role), 403);
            abort_unless(RoleMatrix::canAssign($actorMembership->role, $role), 403);
            abort_if($membership->role === 'owner', 422, 'La propiedad se transfiere mediante una operación específica.');
            if ($membership->role === $role) {
                return;
            }
            $previous = $membership->role;
            $membership->update(['role' => $role]);
            Audit::record('membership.role_changed', $membership, $actor, $tenant->id, ['from' => $previous, 'to' => $role]);
        });
    }

    public function changeStatus(Tenant $tenant, User $actor, TenantMembership $target, string $status): void
    {
        abort_unless(in_array($status, ['active', 'suspended'], true), 422);
        DB::transaction(function () use ($tenant, $actor, $target, $status): void {
            $lockedTenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $actorMembership = $this->activeActor($lockedTenant, $actor);
            $membership = $this->lockedTarget($tenant, $target);
            abort_unless(RoleMatrix::canManage($actorMembership->role, $membership->role), 403);
            abort_if($membership->user_id === $actor->id && $status === 'active' && $membership->status !== 'active', 403);
            if ($membership->status === $status) {
                return;
            }
            if ($membership->role === 'owner' && $status !== 'active') {
                $this->requireAnotherOwner($tenant, $membership);
            }
            $previous = $membership->status;
            $membership->update(['status' => $status]);
            Audit::record('membership.status_changed', $membership, $actor, $tenant->id, ['from' => $previous, 'to' => $status]);
        });
    }

    public function transferOwnership(Tenant $tenant, User $actor, TenantMembership $recipient): void
    {
        DB::transaction(function () use ($tenant, $actor, $recipient): void {
            $lockedTenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $current = $this->activeActor($lockedTenant, $actor);
            abort_unless($current->role === 'owner', 403);
            $next = $this->lockedTarget($tenant, $recipient);
            abort_unless($next->status === 'active' && $next->id !== $current->id, 422);
            $next->update(['role' => 'owner']);
            $current->update(['role' => 'admin']);
            Audit::record('tenant.ownership_transferred', $tenant, $actor, $tenant->id, ['from_user_id' => $actor->id, 'to_user_id' => $next->user_id]);
        });
    }

    private function activeActor(Tenant $tenant, User $actor): TenantMembership
    {
        $membership = TenantMembership::where('tenant_id', $tenant->id)->where('user_id', $actor->id)->where('status', 'active')->first();
        abort_unless($membership && $actor->status === 'active' && $tenant->status === 'active', 403);

        return $membership;
    }

    private function lockedTarget(Tenant $tenant, TenantMembership $target): TenantMembership
    {
        return TenantMembership::where('tenant_id', $tenant->id)->whereKey($target->id)->lockForUpdate()->firstOrFail();
    }

    private function requireAnotherOwner(Tenant $tenant, TenantMembership $owner): void
    {
        $count = TenantMembership::where('tenant_id', $tenant->id)->where('role', 'owner')->where('status', 'active')->where('id', '!=', $owner->id)->count();
        abort_if($count < 1, 422, 'La empresa debe conservar un propietario activo.');
    }
}
