<?php

namespace Tests\Feature;

use App\Models\MobileDevice;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PilotReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role = 'owner', ?Tenant $tenant = null): TenantMembership
    {
        $tenant ??= Tenant::create(['name' => 'Empresa piloto', 'slug' => 'pilot-'.uniqid()]);
        $user = User::factory()->create();

        return TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    public function test_readiness_checks_database_and_cache_without_exposing_details(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['data' => ['status' => 'ok']]);
        $this->getJson('/api/v1/readiness')->assertOk()->assertExactJson([
            'data' => [
                'status' => 'ready',
                'checks' => ['database' => 'ok', 'cache' => 'ok'],
            ],
        ]);
    }

    public function test_support_is_tenant_scoped_and_device_revocation_invalidates_only_its_token(): void
    {
        $owner = $this->member();
        $collector = $this->member('collector', $owner->tenant);
        $foreign = $this->member('collector');
        $collectorToken = $collector->user->createToken('expo-mobile', ['mobile'])->accessToken;
        $otherToken = $collector->user->createToken('expo-mobile', ['mobile'])->accessToken;
        $device = MobileDevice::create([
            'tenant_id' => $owner->tenant_id,
            'membership_id' => $collector->id,
            'access_token_id' => $collectorToken->id,
            'device_identifier' => 'expo-device-identifier-local',
            'last_seen_at' => now(),
        ]);
        $foreignDevice = MobileDevice::create([
            'tenant_id' => $foreign->tenant_id,
            'membership_id' => $foreign->id,
            'device_identifier' => 'expo-device-identifier-foreign',
        ]);

        $this->actingAs($owner->user)->withSession(['active_tenant_id' => $owner->tenant_id])
            ->get('/admin/support')
            ->assertOk()
            ->assertSee($device->id)
            ->assertDontSee($foreignDevice->id);
        $this->post('/admin/support/devices/'.$foreignDevice->id.'/revoke')->assertNotFound();
        $this->post('/admin/support/devices/'.$device->id.'/revoke')->assertRedirect();

        $this->assertNotNull($device->fresh()->revoked_at);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $collectorToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->id]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $owner->tenant_id,
            'action' => 'mobile_device.revoked',
            'subject_id' => $device->id,
        ]);
    }

    public function test_registering_the_same_device_replaces_its_previous_mobile_token(): void
    {
        $collector = $this->member('collector');
        $first = $collector->user->createToken('expo-mobile', ['mobile']);
        $second = $collector->user->createToken('expo-mobile', ['mobile']);
        $identifier = 'expo-device-identifier-relogin';

        $this->withToken($first->plainTextToken)
            ->postJson('/api/v1/tenant/select', ['tenant_id' => $collector->tenant_id])
            ->assertOk();
        $deviceId = $this->withToken($first->plainTextToken)
            ->postJson('/api/v1/mobile-devices', ['device_identifier' => $identifier])
            ->assertOk()
            ->json('data.id');
        Auth::forgetGuards();
        $this->withToken($second->plainTextToken)
            ->postJson('/api/v1/tenant/select', ['tenant_id' => $collector->tenant_id])
            ->assertOk();
        $this->withToken($second->plainTextToken)
            ->postJson('/api/v1/mobile-devices', ['device_identifier' => $identifier])
            ->assertOk()
            ->assertJsonPath('data.id', $deviceId);

        $this->assertDatabaseHas('mobile_devices', [
            'id' => $deviceId,
            'access_token_id' => $second->accessToken->id,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $first->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $second->accessToken->id]);
    }

    public function test_collector_cannot_open_support_or_revoke_a_device(): void
    {
        $collector = $this->member('collector');
        $device = MobileDevice::create([
            'tenant_id' => $collector->tenant_id,
            'membership_id' => $collector->id,
            'device_identifier' => 'expo-device-identifier-local',
        ]);

        $this->actingAs($collector->user)->withSession(['active_tenant_id' => $collector->tenant_id]);
        $this->get('/admin/support')->assertForbidden();
        $this->post('/admin/support/devices/'.$device->id.'/revoke')->assertForbidden();
        $this->assertNull($device->fresh()->revoked_at);
    }

    public function test_private_document_backup_requires_mounted_destination(): void
    {
        Storage::fake('r2');
        Storage::disk('r2')->put('tenants/tenant-a/documents/example.pdf', 'synthetic-document');

        $this->artisan('pilot:backup-documents', ['destination' => storage_path('invalid')])
            ->expectsOutput('El destino debe estar dentro del volumen /backups.')
            ->assertFailed();
        $this->artisan('pilot:backup-documents', ['destination' => '/backups/../escape'])
            ->expectsOutput('El destino debe estar dentro del volumen /backups.')
            ->assertFailed();
    }
}
