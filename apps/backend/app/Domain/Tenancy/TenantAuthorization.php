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
