<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\Audit;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanySettingsController extends Controller
{
    public function show(Request $request): View
    {
        Gate::authorize('tenant.view');
        $tenant = $request->attributes->get('activeTenant');

        return view('admin.company', compact('tenant'));
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('tenant.update');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', Rule::in(['America/Argentina/Buenos_Aires'])],
            'currency' => ['required', 'in:ARS'],
        ]);
        $tenant = $request->attributes->get('activeTenant');
        DB::transaction(function () use ($tenant, $data, $request): void {
            $lockedTenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedTenant->status === 'active', 403);
            $before = $lockedTenant->only(array_keys($data));
            $lockedTenant->update($data);
            Audit::record('tenant.settings_updated', $lockedTenant, $request->user(), $lockedTenant->id, ['before' => $before, 'after' => $data]);
        });

        return back()->with('status', 'La empresa se actualizó.');
    }
}
