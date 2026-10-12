<?php

namespace App\Domain\Tenancy;

use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Http\Request;

final class TenantAuthorization
{
    public static function membership(Request $request, User $user): ?TenantMembership
    {
        $membership = $request->attributes->get('activeMembership');

        if (! $membership instanceof TenantMembership) {
            $activeTenantId = $request->hasSession() ? $request->session()->get('active_tenant_id') : null;
            if ($activeTenantId) {
                $membership = TenantMembership::query()->with('tenant')->where('tenant_id', $activeTenantId)->where('user_id', $user->id)->where('status', 'active')->first();
                if ($membership?->tenant?->status === 'active') {
                    $request->attributes->set('activeTenant', $membership->tenant);
                    $request->attributes->set('activeMembership', $membership);
                }
            }
        }

        return $membership instanceof TenantMembership && $membership->user_id === $user->id
            && $membership->status === 'active' && $membership->tenant?->status === 'active'
            ? $membership : null;
    }

    public static function allows(Request $request, User $user, string $capability): bool
    {
        $membership = self::membership($request, $user);

        return $user->status === 'active' && $membership !== null
            && app(AuthorizationService::class)->allows($membership, $capability);
    }
}
