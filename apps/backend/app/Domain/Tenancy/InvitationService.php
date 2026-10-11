<?php

namespace App\Domain\Tenancy;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\TenantInvitationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InvitationService
{
    public function invite(Tenant $tenant, User $actor, string $email, string $role): TenantInvitation
    {
        $email = Str::lower(trim($email));
        $rawToken = bin2hex(random_bytes(32));
        $invitation = DB::transaction(function () use ($tenant, $actor, $email, $role, $rawToken): TenantInvitation {
            $lockedTenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $actorMembership = TenantMembership::where('tenant_id', $tenant->id)->where('user_id', $actor->id)->where('status', 'active')->first();
            abort_unless($lockedTenant->status === 'active' && $actor->status === 'active' && $actorMembership && RoleMatrix::canAssign($actorMembership->role, $role), 403);
            $existing = User::whereRaw('lower(email) = ?', [$email])->first();
            abort_if($existing && TenantMembership::where('tenant_id', $tenant->id)->where('user_id', $existing->id)->where('status', 'active')->exists(), 422, 'El usuario ya pertenece a esta empresa.');
            TenantInvitation::where('tenant_id', $tenant->id)->whereRaw('lower(email) = ?', [$email])->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $invitation = TenantInvitation::create([
                'tenant_id' => $tenant->id,
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $rawToken),
                'invited_by' => $actor->id,
                'expires_at' => now()->addDays(7),
            ]);
            Audit::record('invitation.created', $invitation, $actor, $tenant->id, ['email' => $email, 'role' => $role]);

            return $invitation;
        });

        Notification::route('mail', $email)->notify(new TenantInvitationMail($tenant->id, $invitation->id, $tenant->name, route('invitations.accept.show', ['token' => $rawToken])));

        return $invitation;
    }

    public function preview(string $token): TenantInvitation
    {
        $invitation = TenantInvitation::with('tenant')->where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_if($invitation->accepted_at || $invitation->revoked_at || $invitation->expires_at->isPast(), 410, 'La invitación ya no está disponible.');
        abort_unless($invitation->tenant->status === 'active', 403);

        return $invitation;
    }

    public function accept(string $token, ?User $currentUser, ?array $registration = null): User
    {
        return DB::transaction(function () use ($token, $currentUser, $registration): User {
            $lookup = TenantInvitation::where('token_hash', hash('sha256', $token))->firstOrFail();
            $lockedTenant = Tenant::whereKey($lookup->tenant_id)->lockForUpdate()->firstOrFail();
            $invitation = TenantInvitation::whereKey($lookup->id)->lockForUpdate()->firstOrFail();
            abort_if($invitation->accepted_at || $invitation->revoked_at || $invitation->expires_at->isPast(), 410, 'La invitación ya no está disponible.');
            abort_unless($lockedTenant->status === 'active', 403);
            $existing = User::whereRaw('lower(email) = ?', [Str::lower($invitation->email)])->first();
            if ($existing) {
                abort_unless($currentUser && $currentUser->id === $existing->id && $existing->status === 'active', 403);
                $user = $existing;
                if (! $user->email_verified_at) {
                    $user->update(['email_verified_at' => now()]);
                }
            } else {
                abort_if($currentUser || ! $registration, 403);
                $user = User::create([
                    'name' => $registration['name'],
                    'email' => $invitation->email,
                    'password' => $registration['password'],
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);
            }
            $membership = TenantMembership::where('tenant_id', $invitation->tenant_id)->where('user_id', $user->id)->lockForUpdate()->first();
            if ($membership) {
                abort_if($membership->role === 'owner', 403);
                $membership->update(['role' => $invitation->role, 'status' => 'active', 'joined_at' => now()]);
            } else {
                $membership = TenantMembership::create(['tenant_id' => $invitation->tenant_id, 'user_id' => $user->id, 'role' => $invitation->role, 'status' => 'active', 'joined_at' => now()]);
            }
            $invitation->update(['accepted_at' => now()]);
            Audit::record('invitation.accepted', $invitation, $user, $invitation->tenant_id, ['membership_id' => $membership->id]);

            return $user;
        });
    }

    public function revoke(Tenant $tenant, User $actor, TenantInvitation $invitation): void
    {
        DB::transaction(function () use ($tenant, $actor, $invitation): void {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $target = TenantInvitation::where('tenant_id', $tenant->id)->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_if($target->accepted_at || $target->revoked_at, 422);
            $target->update(['revoked_at' => now()]);
            Audit::record('invitation.revoked', $target, $actor, $tenant->id, ['email' => $target->email]);
        });
    }
}
