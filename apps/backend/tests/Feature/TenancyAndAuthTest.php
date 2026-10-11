<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class TenancyAndAuthTest extends TestCase
{
    use RefreshDatabase;

    private function adminMembership(User $user, string $slug = 'company-one', string $role = 'admin'): TenantMembership
    {
        $tenant = Tenant::create(['name' => $slug, 'slug' => $slug]);

        return TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => $role]);
    }

    public function test_login_grants_access_to_the_users_single_admin_tenant(): void
    {
        $user = User::factory()->create(['password' => 'very-secure-password']);
        $membership = $this->adminMembership($user);

        $this->post('/login', ['email' => $user->email, 'password' => 'very-secure-password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk()->assertSee('App Cobranzas');
        $this->assertEquals($membership->tenant_id, session('active_tenant_id'));
    }

    public function test_multiple_memberships_require_selection_and_reject_foreign_tenants(): void
    {
        $user = User::factory()->create();
        $first = $this->adminMembership($user);
        $this->adminMembership($user, 'company-two');
        $foreign = Tenant::create(['name' => 'Foreign', 'slug' => 'foreign']);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/tenants/choose');
        $this->post('/tenants/choose', ['tenant_id' => $foreign->id])->assertNotFound();
        $this->post('/tenants/choose', ['tenant_id' => $first->tenant_id])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee('company-one');
    }

    public function test_revoked_membership_and_collector_access_are_scoped(): void
    {
        $user = User::factory()->create();
        $membership = $this->adminMembership($user);
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $membership->delete();
        $this->get('/dashboard')->assertForbidden();
        $this->assertNull(session('active_tenant_id'));

        $this->adminMembership($user, 'collector-company', 'collector');
        $this->get('/dashboard')->assertOk();
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_user_directory_is_scoped_to_the_active_tenant(): void
    {
        $admin = User::factory()->create(['name' => 'Local Admin']);
        $local = $this->adminMembership($admin);
        $colleague = User::factory()->create(['name' => 'Local Colleague']);
        TenantMembership::create(['tenant_id' => $local->tenant_id, 'user_id' => $colleague->id, 'role' => 'collector']);

        $foreignUser = User::factory()->create(['name' => 'Foreign Person']);
        $this->adminMembership($foreignUser, 'other-company');

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('Local Colleague')
            ->assertDontSee('Foreign Person');
        $this->get('/admin/invitations')->assertOk()->assertSee('Sin invitaciones pendientes');
    }

    public function test_password_reset_flow_updates_password_and_unknown_emails_are_not_disclosed(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $this->post('/forgot-password', ['email' => 'unknown@example.com'])->assertSessionHas('status');

        $token = Password::createToken($user);
        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect('/login');
        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }
}
