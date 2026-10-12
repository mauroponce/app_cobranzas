<?php

namespace Tests\Concerns;

use App\Models\TenantMembership;

trait InteractsWithTenantContext
{
    protected function withTenantContext(TenantMembership $membership): void
    {
        $membership->loadMissing('tenant', 'user');
        $this->actingAs($membership->user)->withSession(['active_tenant_id' => $membership->tenant_id]);
        $request = $this->app['request'];
        $request->setUserResolver(fn () => $membership->user);
        $request->attributes->set('activeTenant', $membership->tenant);
        $request->attributes->set('activeMembership', $membership);
    }
}
