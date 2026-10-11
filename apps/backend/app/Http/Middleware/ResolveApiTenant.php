<?php

namespace App\Http\Middleware;

use App\Models\TenantMembership;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->status === 'active', 403);
        $token = $request->user()->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken && $token->tenant_id, 403);
        $membership = TenantMembership::with('tenant')->where('tenant_id', $token->tenant_id)
            ->where('user_id', $request->user()->id)->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))->first();
        if (! $membership) {
            $token->update(['tenant_id' => null]);
            abort(403);
        }
        $request->attributes->set('activeTenant', $membership->tenant);
        $request->attributes->set('activeMembership', $membership);

        return $next($request);
    }
}
