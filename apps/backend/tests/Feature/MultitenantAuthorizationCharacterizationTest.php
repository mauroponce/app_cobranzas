<?php

namespace Tests\Feature;

use App\Domain\Collections\CollectionPortfolioService;
use App\Domain\Collections\CollectionRouteService;
use App\Domain\Tenancy\AuthorizationManagementService;
use App\Domain\Tenancy\AuthorizationService;
use App\Domain\Tenancy\MembershipService;
use App\Models\Customer;
use App\Models\CustomerCollectorAssignment;
use App\Models\Permission;
use App\Models\PlatformAdministrator;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\AuthorizationCatalogSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultitenantAuthorizationCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationCatalogSeeder::class);
    }

    public function test_two_tenants_can_have_different_module_states_in_the_same_installation(): void
    {
        $salesTenant = $this->tenant('Empresa comercial');
        $collectionsTenant = $this->tenant('Empresa de cobranzas');
        $salesOwner = $this->membership($salesTenant, 'owner');
        $collectionsOwner = $this->membership($collectionsTenant, 'owner');

        $this->tenantFeature($salesTenant, 'collections')->update([
            'is_available' => false,
            'is_enabled' => false,
            'disabled_at' => now(),
        ]);
        $this->tenantFeature($collectionsTenant, 'sales')->update([
            'is_available' => false,
            'is_enabled' => false,
            'disabled_at' => now(),
        ]);

        $authorization = app(AuthorizationService::class);

        $this->assertTrue($authorization->allows($salesOwner, 'sales.view'));
        $this->assertFalse($authorization->allows($salesOwner, 'payments.view'));
        $this->assertFalse($authorization->allows($collectionsOwner, 'sales.view'));
        $this->assertTrue($authorization->allows($collectionsOwner, 'payments.view'));
    }

    public function test_same_user_has_different_effective_permissions_in_each_tenant(): void
    {
        $user = User::factory()->create();
        $salesTenant = $this->tenant('Comercial');
        $collectionsTenant = $this->tenant('Cobranzas');
        $this->membership($salesTenant, 'seller', $user);
        $this->membership($collectionsTenant, 'collector', $user);
        $token = $user->createToken('multi-tenant')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $salesTenant->id])->assertOk();
        $salesCapabilities = $this->withToken($token)->getJson('/api/v1/me/capabilities')->assertOk()->json('data');
        $this->assertSame('seller', $salesCapabilities['role']);
        $this->assertSame('all', $salesCapabilities['permissions']['quotations.create']);
        $this->assertArrayNotHasKey('payments.create', $salesCapabilities['permissions']);

        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $collectionsTenant->id])->assertOk();
        $collectionsCapabilities = $this->withToken($token)->getJson('/api/v1/me/capabilities')->assertOk()->json('data');
        $this->assertSame('collector', $collectionsCapabilities['role']);
        $this->assertSame('assigned', $collectionsCapabilities['permissions']['payments.create']);
        $this->assertArrayNotHasKey('quotations.create', $collectionsCapabilities['permissions']);
    }

    public function test_unavailable_and_disabled_modules_both_deny_permissions_but_keep_distinct_states(): void
    {
        $tenant = $this->tenant('Estados de módulos');
        $owner = $this->membership($tenant, 'owner');
        $collections = $this->tenantFeature($tenant, 'collections');
        $sales = $this->tenantFeature($tenant, 'sales');

        $collections->update(['is_enabled' => false, 'disabled_at' => now()]);
        $sales->update(['is_available' => false, 'available_at' => null]);

        $authorization = app(AuthorizationService::class);

        $this->assertFalse($authorization->allows($owner, 'payments.view'));
        $this->assertFalse($authorization->allows($owner, 'sales.view'));
        $this->assertTrue($collections->fresh()->is_available);
        $this->assertFalse($collections->fresh()->is_enabled);
        $this->assertFalse($sales->fresh()->is_available);
        $this->assertTrue($sales->fresh()->is_enabled);
    }

    public function test_individual_denial_wins_and_individual_grant_keeps_its_authorized_scope(): void
    {
        $tenant = $this->tenant('Overrides');
        $owner = $this->membership($tenant, 'owner');
        $collector = $this->membership($tenant, 'collector');
        $viewer = $this->membership($tenant, 'viewer');
        $management = app(AuthorizationManagementService::class);
        $authorization = app(AuthorizationService::class);

        $management->setOverride(
            $tenant,
            $owner->user,
            $collector,
            Permission::where('key', 'payments.create')->firstOrFail(),
            'deny',
            null,
        );
        $management->setOverride(
            $tenant,
            $owner->user,
            $viewer,
            Permission::where('key', 'collections.portfolio.view')->firstOrFail(),
            'allow',
            'assigned',
        );
        $assignedCustomer = $this->customer($owner);
        $this->customer($owner);
        CustomerCollectorAssignment::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $assignedCustomer->id,
            'collector_membership_id' => $viewer->id,
            'assigned_by_membership_id' => $owner->id,
            'assigned_at' => now(),
        ]);

        $this->assertSame(['allowed' => false, 'scope' => null], $authorization->decision($collector->fresh('tenant'), 'payments.create'));
        $this->assertSame(['allowed' => true, 'scope' => 'assigned'], $authorization->decision($viewer->fresh('tenant'), 'collections.portfolio.view'));
        $this->assertSame(
            [$assignedCustomer->id],
            app(CollectionPortfolioService::class)
                ->customers($tenant, $viewer->fresh('tenant'), 'collections.portfolio.view')
                ->pluck('id')
                ->all(),
        );
    }

    public function test_permission_changes_increment_versions_and_are_recalculated_for_an_existing_token(): void
    {
        $tenant = $this->tenant('Versionado');
        $owner = $this->membership($tenant, 'owner');
        $collector = $this->membership($tenant, 'collector');
        $token = $collector->user->createToken('stale-device')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $tenant->id])->assertOk();

        $cachedCapabilities = $this->withToken($token)->getJson('/api/v1/me/capabilities')->assertOk()->json('data');
        $this->assertArrayHasKey('payments.view', $cachedCapabilities['permissions']);

        app(AuthorizationManagementService::class)->setOverride(
            $tenant,
            $owner->user,
            $collector,
            Permission::where('key', 'payments.view')->firstOrFail(),
            'deny',
            null,
        );

        $this->withToken($token)->getJson('/api/v1/payments')->assertForbidden();
        $freshCapabilities = $this->withToken($token)->getJson('/api/v1/me/capabilities')->assertOk()->json('data');

        $this->assertArrayNotHasKey('payments.view', $freshCapabilities['permissions']);
        $this->assertGreaterThan($cachedCapabilities['authorization_version']['tenant'], $freshCapabilities['authorization_version']['tenant']);
        $this->assertGreaterThan($cachedCapabilities['authorization_version']['membership'], $freshCapabilities['authorization_version']['membership']);
    }

    public function test_role_changes_are_recalculated_but_do_not_increment_authorization_versions(): void
    {
        $tenant = $this->tenant('Cambio de rol');
        $owner = $this->membership($tenant, 'owner');
        $seller = $this->membership($tenant, 'seller');
        $token = $seller->user->createToken('role-change')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $tenant->id])->assertOk();
        $before = $this->withToken($token)->getJson('/api/v1/me/capabilities')->assertOk()->json('data');

        app(MembershipService::class)->changeRole($tenant, $owner->user, $seller, 'collector');

        $after = $this->withToken($token)->getJson('/api/v1/me/capabilities')->assertOk()->json('data');
        $this->assertSame('collector', $after['role']);
        $this->assertSame('assigned', $after['permissions']['payments.create']);
        $this->assertSame($before['authorization_version'], $after['authorization_version']);
    }

    public function test_revoked_membership_rejects_requests_from_an_existing_token(): void
    {
        $tenant = $this->tenant('Revocaciones');
        $collector = $this->membership($tenant, 'collector');
        $token = $collector->user->createToken('revoked-membership')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $tenant->id])->assertOk();

        $collector->update(['status' => 'suspended']);

        $this->withToken($token)->getJson('/api/v1/payments')->assertForbidden();
    }

    public function test_resource_access_never_crosses_the_selected_tenant(): void
    {
        $localTenant = $this->tenant('Empresa local');
        $foreignTenant = $this->tenant('Empresa externa');
        $localOwner = $this->membership($localTenant, 'owner');
        $foreignOwner = $this->membership($foreignTenant, 'owner');
        $foreignCustomer = $this->customer($foreignOwner);
        $token = $localOwner->user->createToken('tenant-bound')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $localTenant->id])->assertOk();

        $this->withToken($token)->getJson('/api/v1/customers/'.$foreignCustomer->id)->assertNotFound();
    }

    public function test_platform_operator_has_no_implicit_access_to_financial_tenant_data(): void
    {
        $tenant = $this->tenant('Empresa operativa');
        $this->membership($tenant, 'owner');
        $platformUser = User::factory()->create();
        PlatformAdministrator::create(['user_id' => $platformUser->id]);
        $token = $platformUser->createToken('platform-only')->plainTextToken;

        $this->actingAs($platformUser)->get('/platform')->assertOk();
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $tenant->id])->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/payments')->assertForbidden();
    }

    public function test_legacy_permissions_endpoint_reports_the_role_preset_not_effective_permissions(): void
    {
        $tenant = $this->tenant('Contrato legado');
        $owner = $this->membership($tenant, 'owner');
        $collector = $this->membership($tenant, 'collector');
        app(AuthorizationManagementService::class)->setOverride(
            $tenant,
            $owner->user,
            $collector,
            Permission::where('key', 'payments.create')->firstOrFail(),
            'deny',
            null,
        );
        $token = $collector->user->createToken('legacy-contract')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $tenant->id])->assertOk();

        $this->withToken($token)->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonFragment(['payments.create']);
        $this->withToken($token)->getJson('/api/v1/me/capabilities')
            ->assertOk()
            ->assertJsonMissingPath('data.permissions.payments.create');
    }

    public function test_current_route_assignment_still_requires_the_primary_collector_role(): void
    {
        $tenant = $this->tenant('Responsabilidades combinables');
        $owner = $this->membership($tenant, 'owner');
        $sellerCollector = $this->membership($tenant, 'seller');
        app(AuthorizationManagementService::class)->setOverride(
            $tenant,
            $owner->user,
            $sellerCollector,
            Permission::where('key', 'payments.create')->firstOrFail(),
            'allow',
            'assigned',
        );
        $customer = $this->customer($owner);
        $sale = $this->sale($owner, $customer);

        $this->assertTrue(app(CollectionPortfolioService::class)->eligibleCollector($tenant, $sellerCollector->fresh('tenant')));

        $this->expectException(ModelNotFoundException::class);
        app(CollectionRouteService::class)->program($tenant, $owner->user, $sellerCollector->id, today()->toDateString(), [$sale->id]);
    }

    private function tenant(string $name): Tenant
    {
        $tenant = Tenant::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(8))]);
        $this->seed(AuthorizationCatalogSeeder::class);

        return $tenant->fresh();
    }

    private function membership(Tenant $tenant, string $role, ?User $user = null): TenantMembership
    {
        $user ??= User::factory()->create();

        return TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ])->fresh(['tenant', 'user']);
    }

    private function tenantFeature(Tenant $tenant, string $key): TenantFeature
    {
        return TenantFeature::forTenant($tenant)
            ->whereHas('feature', fn ($query) => $query->where('key', $key))
            ->firstOrFail();
    }

    private function customer(TenantMembership $membership): Customer
    {
        return Customer::create([
            'tenant_id' => $membership->tenant_id,
            'customer_number' => 'CLI-'.Str::upper(Str::random(8)),
            'first_name' => 'Cliente',
            'last_name' => 'Caracterización',
            'status' => 'active',
            'created_by' => $membership->user_id,
        ]);
    }

    private function sale(TenantMembership $membership, Customer $customer): Sale
    {
        return Sale::create([
            'tenant_id' => $membership->tenant_id,
            'sale_number' => 'VEN-'.Str::upper(Str::random(8)),
            'customer_id' => $customer->id,
            'seller_id' => $membership->user_id,
            'sale_type' => 'cash',
            'status' => 'confirmed',
            'total_amount' => '10.00',
            'currency' => 'ARS',
            'subtotal' => '10.00',
            'discount_amount' => '0.00',
            'down_payment_amount' => '10.00',
            'financed_principal' => '0.00',
            'financing_charge_amount' => '0.00',
            'financed_total' => '0.00',
        ]);
    }
}
