<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\Audit;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformTenantController extends Controller
{
    public function index(): View
    {
        Gate::authorize('platform.access');
        $tenants = Tenant::withCount(['memberships as active_users_count' => fn ($query) => $query->where('status', 'active')])->orderBy('name')->paginate(20);

        return view('platform.tenants', compact('tenants'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('platform.access');
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'alpha_dash', 'lowercase', 'max:100', Rule::unique('tenants')], 'owner_email' => ['required', 'email', Rule::exists('users', 'email')]]);
        DB::transaction(function () use ($request, $data): void {
            $owner = User::where('email', $data['owner_email'])->where('status', 'active')->firstOrFail();
            $tenant = Tenant::create(['name' => $data['name'], 'slug' => Str::lower($data['slug']), 'status' => 'active', 'timezone' => 'America/Argentina/Buenos_Aires', 'currency' => 'ARS']);
            TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
            Audit::record('tenant.created', $tenant, $request->user(), $tenant->id, ['owner_user_id' => $owner->id]);
        });

        return back()->with('status', 'La empresa se creó.');
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        Gate::authorize('platform.access');
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'status' => ['required', Rule::in(['active', 'suspended'])]]);
        DB::transaction(function () use ($tenant, $data, $request): void {
            $lockedTenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $before = $lockedTenant->only(['name', 'status']);
            $lockedTenant->update($data);
            Audit::record('tenant.platform_updated', $lockedTenant, $request->user(), $lockedTenant->id, ['before' => $before, 'after' => $data]);
        });

        return back()->with('status', 'La empresa se actualizó.');
    }
}
