<?php

namespace Tests\Feature;

use App\Domain\Tenancy\InvitationService;
use App\Models\PlatformAdministrator;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\TenantInvitationMail;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccessManagementTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role = 'owner', ?Tenant $tenant = null): TenantMembership
    {
        $tenant ??= Tenant::create(['name' => 'Trotamundos', 'slug' => 'trotamundos-'.uniqid()]);
        $user = User::factory()->create();

        return TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active', 'joined_at' => now()]);
    }

    public function test_login_failure_and_user_without_membership(): void
    {
        $user = User::factory()->create(['password' => 'secure-password-for-tests']);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors();
        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }

    public function test_users_are_scoped_and_role_and_status_changes_are_audited(): void
    {
        $owner = $this->member();
        $seller = $this->member('seller', $owner->tenant);
        $foreign = $this->member('seller');
        $this->actingAs($owner->user)->get('/admin/users/'.$foreign->id)->assertNotFound();
        $this->patch('/admin/users/'.$foreign->id.'/role', ['role' => 'admin'])->assertNotFound();
        $this->patch('/admin/users/'.$seller->id.'/role', ['role' => 'collector'])->assertRedirect();
        $this->assertSame('collector', $seller->fresh()->role);
        $this->patch('/admin/users/'.$seller->id.'/status', ['status' => 'suspended'])->assertRedirect();
        $this->assertSame('suspended', $seller->fresh()->status);
        $this->assertDatabaseHas('audit_events', ['action' => 'membership.role_changed', 'tenant_id' => $owner->tenant_id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'membership.status_changed', 'tenant_id' => $owner->tenant_id]);
        $this->actingAs($seller->user)->get('/dashboard')->assertForbidden();
    }

    public function test_admin_cannot_escalate_or_suspend_last_owner(): void
    {
        $owner = $this->member();
        $admin = $this->member('admin', $owner->tenant);
        $this->actingAs($admin->user)->patch('/admin/users/'.$owner->id.'/status', ['status' => 'suspended'])->assertForbidden();
        $this->patch('/admin/users/'.$admin->id.'/role', ['role' => 'owner'])->assertForbidden();
        $this->actingAs($owner->user)->patch('/admin/users/'.$owner->id.'/status', ['status' => 'suspended'])->assertStatus(422);
        $this->assertSame('active', $owner->fresh()->status);
    }

    public function test_owner_can_transfer_property_explicitly(): void
    {
        $owner = $this->member();
        $admin = $this->member('admin', $owner->tenant);
        $this->actingAs($owner->user)->post('/admin/users/'.$admin->id.'/transfer-ownership')->assertRedirect();
        $this->assertSame('admin', $owner->fresh()->role);
        $this->assertSame('owner', $admin->fresh()->role);
        $this->assertDatabaseHas('audit_events', ['action' => 'tenant.ownership_transferred']);
    }

    public function test_invitation_for_new_user_uses_hashed_token_and_cannot_be_replayed(): void
    {
        Notification::fake();
        $owner = $this->member();
        $this->actingAs($owner->user)->post('/admin/invitations', ['email' => 'new@example.test', 'role' => 'collector'])->assertRedirect();
        $invitation = TenantInvitation::firstOrFail();
        $this->assertSame(64, strlen($invitation->token_hash));
        Notification::assertSentOnDemand(TenantInvitationMail::class);
        $notification = Notification::sent(new AnonymousNotifiable, TenantInvitationMail::class)->first();
        $url = $notification->toMail((object) [])->actionUrl;
        $token = basename($url);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->post('/logout');
        $this->post('/invitations/accept/'.$token, ['name' => 'Nuevo Usuario', 'password' => 'secure-password-123', 'password_confirmation' => 'secure-password-123'])->assertRedirect('/dashboard');
        $this->assertDatabaseHas('tenant_memberships', ['tenant_id' => $owner->tenant_id, 'role' => 'collector', 'status' => 'active']);
        $this->post('/invitations/accept/'.$token)->assertStatus(410);
        $this->assertDatabaseHas('audit_events', ['action' => 'invitation.accepted']);
    }

    public function test_existing_user_must_sign_in_with_invited_email(): void
    {
        Notification::fake();
        $owner = $this->member();
        $existing = User::factory()->create(['email' => 'existing@example.test']);
        $other = User::factory()->create();
        $service = app(InvitationService::class);
        $service->invite($owner->tenant, $owner->user, $existing->email, 'viewer');
        $token = basename(Notification::sent(new AnonymousNotifiable, TenantInvitationMail::class)->first()->toMail((object) [])->actionUrl);
        $this->actingAs($other)->post('/invitations/accept/'.$token)->assertForbidden();
        $this->actingAs($existing)->post('/invitations/accept/'.$token)->assertRedirect('/dashboard');
        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $existing->id, 'role' => 'viewer']);
    }

    public function test_expired_and_revoked_invitations_are_rejected(): void
    {
        Notification::fake();
        $owner = $this->member();
        $service = app(InvitationService::class);
        $expired = $service->invite($owner->tenant, $owner->user, 'expired@example.test', 'viewer');
        $token = basename(Notification::sent(new AnonymousNotifiable, TenantInvitationMail::class)->first()->toMail((object) [])->actionUrl);
        $expired->update(['expires_at' => now()->subDay()]);
        $this->get('/invitations/accept/'.$token)->assertStatus(410);
        $new = $service->invite($owner->tenant, $owner->user, 'revoked@example.test', 'viewer');
        $url = Notification::sent(new AnonymousNotifiable, TenantInvitationMail::class)->last()->toMail((object) [])->actionUrl;
        $this->actingAs($owner->user)->delete('/admin/invitations/'.$new->id)->assertRedirect();
        $this->assertFalse(Notification::sent(new AnonymousNotifiable, TenantInvitationMail::class)->last()->shouldSend(new AnonymousNotifiable, 'mail'));
        $this->get('/invitations/accept/'.basename($url))->assertStatus(410);
    }

    public function test_only_platform_admin_can_manage_tenants_without_operational_access(): void
    {
        $owner = $this->member();
        $platform = User::factory()->create();
        PlatformAdministrator::create(['user_id' => $platform->id]);
        $this->actingAs($owner->user)->get('/platform')->assertForbidden();
        $this->actingAs($platform)->get('/platform')->assertOk()->assertSee('Trotamundos');
        $this->get('/admin/users')->assertForbidden();
        $this->patch('/platform/tenants/'.$owner->tenant_id, ['name' => 'Trotamundos', 'status' => 'suspended'])->assertRedirect();
        $this->assertSame('suspended', $owner->tenant->fresh()->status);
        $this->actingAs($owner->user)->get('/dashboard')->assertForbidden();
    }

    public function test_company_settings_and_platform_creation_are_audited(): void
    {
        $owner = $this->member();
        $this->actingAs($owner->user)->patch('/admin/settings/company', [
            'name' => 'Trotamundos Sur', 'timezone' => 'America/Argentina/Cordoba', 'currency' => 'ARS',
        ])->assertSessionHasErrors('timezone');
        $this->actingAs($owner->user)->patch('/admin/settings/company', [
            'name' => 'Trotamundos Sur', 'timezone' => 'America/Argentina/Buenos_Aires', 'currency' => 'ARS',
        ])->assertRedirect();
        $this->assertSame('Trotamundos Sur', $owner->tenant->fresh()->name);
        $this->assertSame('America/Argentina/Buenos_Aires', $owner->tenant->fresh()->timezone);
        $this->assertDatabaseHas('audit_events', ['action' => 'tenant.settings_updated']);
        $platform = User::factory()->create();
        PlatformAdministrator::create(['user_id' => $platform->id]);
        $this->actingAs($platform)->post('/platform/tenants', [
            'name' => 'Nueva Empresa', 'slug' => 'nueva-empresa', 'owner_email' => $owner->user->email,
        ])->assertRedirect();
        $newTenant = Tenant::where('slug', 'nueva-empresa')->firstOrFail();
        $this->assertSame('America/Argentina/Buenos_Aires', $newTenant->timezone);
        $this->assertDatabaseHas('tenant_memberships', ['tenant_id' => $newTenant->id, 'user_id' => $owner->user_id, 'role' => 'owner', 'status' => 'active']);
        $this->assertDatabaseHas('audit_events', ['action' => 'tenant.created', 'tenant_id' => $newTenant->id]);
    }

    public function test_database_rejects_a_tenant_outside_the_buenos_aires_timezone(): void
    {
        $this->expectException(QueryException::class);

        Tenant::create([
            'name' => 'Otra zona',
            'slug' => 'otra-zona',
            'timezone' => 'UTC',
            'currency' => 'ARS',
        ]);
    }

    public function test_pending_invitation_is_unique_per_tenant_and_email(): void
    {
        Notification::fake();
        $owner = $this->member();
        $service = app(InvitationService::class);
        $first = $service->invite($owner->tenant, $owner->user, 'case@example.test', 'seller');
        $second = $service->invite($owner->tenant, $owner->user, 'CASE@example.test', 'viewer');
        $this->assertNotNull($first->fresh()->revoked_at);
        $this->assertNull($second->fresh()->revoked_at);
        $this->assertSame(1, TenantInvitation::where('tenant_id', $owner->tenant_id)->whereNull('revoked_at')->count());
    }

    public function test_search_and_role_filters_never_reveal_foreign_memberships(): void
    {
        $owner = $this->member();
        $local = $this->member('seller', $owner->tenant);
        $local->user->update(['name' => 'Carla Local']);
        $foreign = $this->member('seller');
        $foreign->user->update(['name' => 'Carla Externa']);
        $this->actingAs($owner->user)->get('/admin/users?search=Carla&role=seller')
            ->assertOk()->assertSee('Carla Local')->assertDontSee('Carla Externa');
    }

    public function test_postgresql_rejects_invalid_membership_role(): void
    {
        $owner = $this->member();
        $this->expectException(QueryException::class);
        TenantMembership::create([
            'tenant_id' => $owner->tenant_id,
            'user_id' => User::factory()->create()->id,
            'role' => 'platform_admin',
            'status' => 'active',
        ]);
    }

    public function test_platform_administrator_grants_and_revocations_are_audited(): void
    {
        $user = User::factory()->create();
        $this->artisan('platform:administrator', ['email' => $user->email])->assertSuccessful();
        $this->assertDatabaseHas('platform_administrators', ['user_id' => $user->id]);
        $this->artisan('platform:administrator', ['email' => $user->email, '--revoke' => true])->assertSuccessful();
        $this->assertDatabaseMissing('platform_administrators', ['user_id' => $user->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'platform_admin.granted']);
        $this->assertDatabaseHas('audit_events', ['action' => 'platform_admin.revoked']);
    }

    public function test_api_context_is_bound_to_authorized_token_and_revalidated(): void
    {
        $owner = $this->member();
        $foreign = $this->member();
        $token = $owner->user->createToken('test')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $foreign->tenant_id])->assertForbidden();
        $this->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $this->getJson('/api/v1/permissions')->assertOk()->assertJsonPath('data.role', 'owner');
        $this->getJson('/api/v1/profile')->assertOk()->assertJsonPath('data.tenant.id', $owner->tenant_id);
        $owner->update(['status' => 'suspended']);
        $this->getJson('/api/v1/profile')->assertForbidden();
    }
}
