<?php

namespace App\Http\Middleware;

use App\Models\TenantMembership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveAdminTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()->status !== 'active', 403);

        $memberships = TenantMembership::query()
            ->with('tenant')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->get();

        if ($memberships->isEmpty()) {
            $request->session()->forget('active_tenant_id');
            abort(403);
        }

        $activeId = $request->session()->get('active_tenant_id');

        if ($activeId !== null) {
            $membership = $memberships->firstWhere('tenant_id', $activeId);
            if ($membership === null) {
                $request->session()->forget('active_tenant_id');
                abort(403);
            }
        } elseif ($memberships->count() === 1) {
            $membership = $memberships->first();
            $request->session()->put('active_tenant_id', $membership->tenant_id);
        } else {
            return redirect()->route('tenants.choose');
        }

        $request->attributes->set('activeTenant', $membership->tenant);
        $request->attributes->set('activeMembership', $membership);

        return $next($request);
    }
}
