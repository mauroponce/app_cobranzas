<?php

namespace App\Domain\Tenancy;

use App\Models\MembershipPermissionOverride;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AuthorizationManagementService
{
    public function setFeature(Tenant $tenant, User $actor, TenantFeature $feature, bool $enabled): void
    {
        $this->requireManage($tenant, $actor);
        abort_unless($feature->tenant_id === $tenant->id && $feature->is_available, 422);
        DB::transaction(function () use ($tenant, $actor, $feature, $enabled): void {
            $feature = TenantFeature::forTenant($tenant)->whereKey($feature->id)->lockForUpdate()->firstOrFail();
            if (! $feature->is_available) {
                throw ValidationException::withMessages(['feature' => 'La plataforma no habilitó este módulo.']);
            }
            $previous = $feature->is_enabled;
            $feature->update(['is_enabled' => $enabled, 'enabled_at' => $enabled ? now() : null, 'disabled_at' => $enabled ? null : now(), 'updated_by' => $actor->id]);
            $this->bump($tenant->id, null);
            Audit::record('tenant_feature.changed', $feature, $actor, $tenant->id, ['from' => $previous, 'to' => $enabled]);
        });
    }

    public function setOverride(Tenant $tenant, User $actor, TenantMembership $subject, Permission $permission, string $effect, ?string $scope): void
    {
        $actorMembership = $this->requireManage($tenant, $actor);
        abort_unless($subject->tenant_id === $tenant->id, 404);
        abort_unless(in_array($effect, ['allow', 'deny'], true), 422);
        if ($effect === 'allow') {
            $actorDecision = app(AuthorizationService::class)->decision($actorMembership, $permission->key);
            if (! $actorDecision['allowed'] || ! in_array($scope, $permission->allowed_scopes ?: [], true) || ! $this->canDelegateScope($actorDecision['scope'], $scope)) {
                throw ValidationException::withMessages(['permission' => 'No podés delegar este permiso o alcance.']);
            }
        }
        DB::transaction(function () use ($tenant, $actor, $subject, $permission, $effect, $scope): void {
            $override = MembershipPermissionOverride::updateOrCreate(
                ['tenant_id' => $tenant->id, 'membership_id' => $subject->id, 'permission_id' => $permission->id],
                ['effect' => $effect, 'scope' => $effect === 'allow' ? $scope : null, 'created_by' => $actor->id],
            );
            $this->bump($tenant->id, $subject->id);
            Audit::record('membership_permission.changed', $override, $actor, $tenant->id, ['effect' => $effect, 'scope' => $effect === 'allow' ? $scope : null]);
        });
    }

    public function resetOverride(Tenant $tenant, User $actor, TenantMembership $subject, Permission $permission): void
    {
        $this->requireManage($tenant, $actor);
        abort_unless($subject->tenant_id === $tenant->id, 404);
        $override = MembershipPermissionOverride::forTenant($tenant)->where('membership_id', $subject->id)->where('permission_id', $permission->id)->first();
        if ($override) {
            $override->delete();
            $this->bump($tenant->id, $subject->id);
            Audit::record('membership_permission.reset', $subject, $actor, $tenant->id, ['permission' => $permission->key]);
        }
    }

    private function requireManage(Tenant $tenant, User $actor): TenantMembership
    {
        $membership = TenantMembership::forTenant($tenant)->where('user_id', $actor->id)->where('status', 'active')->first();
        abort_unless($membership && app(AuthorizationService::class)->allows($membership, 'authorization.manage'), 403);

        return $membership;
    }

    private function canDelegateScope(?string $actorScope, ?string $requested): bool
    {
        return $actorScope === 'all' || $actorScope === $requested;
    }

    private function bump(string $tenantId, ?string $membershipId): void
    {
        Tenant::whereKey($tenantId)->increment('authorization_version');
        if ($membershipId) {
            TenantMembership::forTenant($tenantId)->whereKey($membershipId)->increment('authorization_version');
        }
    }
}
