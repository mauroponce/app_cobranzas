<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\MembershipService;
use App\Domain\Tenancy\RoleMatrix;
use App\Models\TenantMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantUserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('users.view');
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(RoleMatrix::ROLES)],
            'status' => ['nullable', Rule::in(['active', 'invited', 'suspended'])],
        ]);
        $memberships = TenantMembership::query()->with('user')
            ->where('tenant_id', $request->attributes->get('activeTenant')->id)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas('user', fn ($users) => $users->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%")))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('created_at')->paginate(15)->withQueryString();

        return view('admin.users', compact('memberships', 'filters'));
    }

    public function show(Request $request, TenantMembership $membership): View
    {
        Gate::authorize('users.view');
        $this->assertTenant($request, $membership);
        $membership->load('user');

        return view('admin.user', compact('membership'));
    }

    public function updateRole(Request $request, TenantMembership $membership, MembershipService $service): RedirectResponse
    {
        Gate::authorize('users.assign_roles');
        $this->assertTenant($request, $membership);
        $data = $request->validate(['role' => ['required', Rule::in(RoleMatrix::ROLES)]]);
        $service->changeRole($request->attributes->get('activeTenant'), $request->user(), $membership, $data['role']);

        return back()->with('status', 'El rol se actualizó.');
    }

    public function updateStatus(Request $request, TenantMembership $membership, MembershipService $service): RedirectResponse
    {
        Gate::authorize('users.disable');
        $this->assertTenant($request, $membership);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended'])]]);
        $service->changeStatus($request->attributes->get('activeTenant'), $request->user(), $membership, $data['status']);

        return back()->with('status', 'El acceso se actualizó.');
    }

    public function transfer(Request $request, TenantMembership $membership, MembershipService $service): RedirectResponse
    {
        $this->assertTenant($request, $membership);
        $service->transferOwnership($request->attributes->get('activeTenant'), $request->user(), $membership);

        return back()->with('status', 'La propiedad se transfirió.');
    }

    private function assertTenant(Request $request, TenantMembership $membership): void
    {
        abort_unless($membership->tenant_id === $request->attributes->get('activeTenant')->id, 404);
    }
}
