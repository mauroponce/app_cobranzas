<?php

namespace App\Livewire;

use App\Domain\Tenancy\AuthorizationManagementService;
use App\Domain\Tenancy\AuthorizationService;
use App\Models\MembershipPermissionOverride;
use App\Models\Permission;
use App\Models\TenantFeature;
use App\Models\TenantMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AuthorizationManager extends Component
{
    public string $membershipId = '';

    public function setFeature(string $featureId, bool $enabled, AuthorizationManagementService $service): void
    {
        Gate::authorize('authorization.manage');
        $service->setFeature(request()->attributes->get('activeTenant'), auth()->user(), TenantFeature::findOrFail($featureId), $enabled);
    }

    public function setOverride(string $permissionId, string $effect, ?string $scope, AuthorizationManagementService $service): void
    {
        Gate::authorize('authorization.manage');
        $this->validate(['membershipId' => ['required', 'uuid']]);
        $service->setOverride(request()->attributes->get('activeTenant'), auth()->user(), TenantMembership::findOrFail($this->membershipId), Permission::findOrFail($permissionId), $effect, $scope);
    }

    public function resetOverride(string $permissionId, AuthorizationManagementService $service): void
    {
        Gate::authorize('authorization.manage');
        $service->resetOverride(request()->attributes->get('activeTenant'), auth()->user(), TenantMembership::findOrFail($this->membershipId), Permission::findOrFail($permissionId));
    }

    public function render(AuthorizationService $authorization): View
    {
        Gate::authorize('authorization.view');
        $tenant = request()->attributes->get('activeTenant');
        $memberships = TenantMembership::forTenant($tenant)->with('user')->where('status', 'active')->orderBy('role')->get();
        $subject = $this->membershipId ? $memberships->firstWhere('id', $this->membershipId) : null;
        $overrides = $subject ? MembershipPermissionOverride::forTenant($tenant)->where('membership_id', $subject->id)->get()->keyBy('permission_id') : collect();

        return view('livewire.authorization-manager', ['features' => TenantFeature::forTenant($tenant)->with('feature')->get(), 'memberships' => $memberships, 'subject' => $subject, 'permissions' => Permission::with('feature')->orderBy('key')->get(), 'overrides' => $overrides, 'authorization' => $authorization]);
    }
}
