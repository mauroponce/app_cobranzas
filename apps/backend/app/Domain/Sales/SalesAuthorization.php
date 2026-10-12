<?php

namespace App\Domain\Sales;

use App\Domain\Tenancy\AuthorizationService;
use App\Models\TenantMembership;
use App\Models\User;

final class SalesAuthorization
{
    public function require(string $tenantId, User $actor, string $capability): void
    {
        abort_unless($actor->status === 'active', 403);
        $membership = TenantMembership::forTenant($tenantId)->with('tenant')->where('user_id', $actor->id)->where('status', 'active')->first();
        abort_unless($membership && app(AuthorizationService::class)->allows($membership, $capability), 403);
    }
}
