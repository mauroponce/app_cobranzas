<?php

namespace App\Domain\Tenancy;

use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Http\Request;

final class LivewireTenantContext
{
    public function resolve(Request $request, User $user, string $tenantId): TenantMembership
    {
        $user = $user->fresh() ?? $user;
        $membership = TenantMembership::query()->with('tenant')->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('status', 'active')->first();
        abort_unless($user->status === 'active' && $membership?->tenant?->status === 'active', 403);
        $request->attributes->set('activeTenant', $membership->tenant);
        $request->attributes->set('activeMembership', $membership);

        return $membership;
    }
}
