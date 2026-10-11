<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\InvitationService;
use App\Domain\Tenancy\RoleMatrix;
use App\Models\TenantInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantInvitationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('users.view');
        $invitations = TenantInvitation::where('tenant_id', $request->attributes->get('activeTenant')->id)
            ->whereNull('accepted_at')->whereNull('revoked_at')->latest()->paginate(15);

        return view('admin.invitations', compact('invitations'));
    }

    public function store(Request $request, InvitationService $service): RedirectResponse
    {
        Gate::authorize('users.create');
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'role' => ['required', Rule::in(RoleMatrix::ROLES)]]);
        $service->invite($request->attributes->get('activeTenant'), $request->user(), $data['email'], $data['role']);

        return back()->with('status', 'La invitación se envió.');
    }

    public function resend(Request $request, TenantInvitation $invitation, InvitationService $service): RedirectResponse
    {
        Gate::authorize('users.create');
        $this->assertTenant($request, $invitation);
        abort_if($invitation->accepted_at || $invitation->revoked_at, 422);
        $service->invite($request->attributes->get('activeTenant'), $request->user(), $invitation->email, $invitation->role);

        return back()->with('status', 'Se envió una nueva invitación.');
    }

    public function revoke(Request $request, TenantInvitation $invitation, InvitationService $service): RedirectResponse
    {
        Gate::authorize('users.create');
        $this->assertTenant($request, $invitation);
        $service->revoke($request->attributes->get('activeTenant'), $request->user(), $invitation);

        return back()->with('status', 'La invitación se revocó.');
    }

    private function assertTenant(Request $request, TenantInvitation $invitation): void
    {
        abort_unless($invitation->tenant_id === $request->attributes->get('activeTenant')->id, 404);
    }
}
