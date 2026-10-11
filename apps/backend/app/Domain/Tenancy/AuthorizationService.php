<?php

namespace App\Domain\Tenancy;

use App\Models\MembershipPermissionOverride;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\TenantFeature;
use App\Models\TenantMembership;

final class AuthorizationService
{
    public function decision(TenantMembership $membership, string $capability): array
    {
        if ($membership->status !== 'active' || $membership->tenant?->status !== 'active') {
            return ['allowed' => false, 'scope' => null];
        }
        $permission = Permission::query()->with('feature')->where('key', $capability)->first();
        if (! $permission) {
            return ['allowed' => in_array($capability, RoleMatrix::permissions($membership->role), true), 'scope' => 'all'];
        }
        $feature = TenantFeature::query()->where('tenant_id', $membership->tenant_id)->where('feature_id', $permission->feature_id)->first();
        if (! $permission->feature?->is_active || ! $feature?->is_enabled) {
            return ['allowed' => false, 'scope' => null];
        }
        $override = MembershipPermissionOverride::query()->where('tenant_id', $membership->tenant_id)->where('membership_id', $membership->id)->where('permission_id', $permission->id)->first();
        if ($override) {
            return ['allowed' => $override->effect === 'allow', 'scope' => $override->effect === 'allow' ? $override->scope : null];
        }
        $role = RolePermission::query()->where('role', $membership->role)->where('permission_id', $permission->id)->first();

        return ['allowed' => $role !== null, 'scope' => $role?->scope];
    }

    public function allows(TenantMembership $membership, string $capability): bool
    {
        return $this->decision($membership, $capability)['allowed'];
    }
}
