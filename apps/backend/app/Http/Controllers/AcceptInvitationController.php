<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\InvitationService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AcceptInvitationController extends Controller
{
    public function show(string $token, InvitationService $service): View
    {
        $invitation = $service->preview($token);

        return view('auth.accept-invitation', compact('invitation', 'token'));
    }

    public function accept(Request $request, string $token, InvitationService $service): RedirectResponse
    {
        $invitation = $service->preview($token);
        $registration = null;
        if (! $request->user() && ! User::whereRaw('lower(email) = ?', [strtolower($invitation->email)])->exists()) {
            $registration = $request->validate(['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'confirmed', Password::min(12)]]);
        }
        $user = $service->accept($token, $request->user(), $registration);
        if (! $request->user()) {
            Auth::login($user);
            $request->session()->regenerate();
        }
        $request->session()->put('active_tenant_id', $invitation->tenant_id);

        return redirect()->route('dashboard')->with('status', 'Tu acceso a la empresa está activo.');
    }
}
