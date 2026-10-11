<?php

namespace App\Http\Controllers;

use App\Models\TenantMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantSelectionController extends Controller
{
    public function show(Request $request): View
    {
        abort_unless($request->user()->status === 'active', 403);
        $memberships = TenantMembership::with('tenant')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->get();

        return view('tenants.choose', compact('memberships'));
    }

    public function select(Request $request): RedirectResponse
    {
        abort_unless($request->user()->status === 'active', 403);
        $input = $request->validate(['tenant_id' => ['required', 'uuid']]);
        $membership = TenantMembership::query()
            ->where('user_id', $request->user()->id)
            ->where('tenant_id', $input['tenant_id'])
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->firstOrFail();

        $request->session()->put('active_tenant_id', $membership->tenant_id);

        return redirect()->route('dashboard');
    }
}
