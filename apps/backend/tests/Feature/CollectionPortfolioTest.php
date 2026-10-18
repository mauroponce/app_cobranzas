<?php

namespace Tests\Feature;

use App\Domain\Collections\CollectionAssignmentService;
use App\Domain\Collections\CollectionDashboardNotifier;
use App\Domain\Collections\CollectionRouteService;
use App\Domain\Collections\CollectionWorkflowService;
use App\Domain\Collections\PaymentService;
use App\Domain\Tenancy\AuthorizationManagementService;
use App\Livewire\CollectionPortfolioManager;
use App\Livewire\CollectionsDashboard;
use App\Livewire\CollectionStructuresManager;
use App\Models\CollectionDeclaration;
use App\Models\CollectionRoute;
use App\Models\CollectionTeam;
use App\Models\CollectionTeamMember;
use App\Models\CollectionVisit;
use App\Models\CollectionZone;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Permission;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\AuthorizationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CollectionPortfolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationCatalogSeeder::class);
    }

    private function member(string $role, ?Tenant $tenant = null): TenantMembership
    {
        $tenant ??= Tenant::create(['name' => 'Cartera '.uniqid(), 'slug' => 'cartera-'.uniqid()]);
        $membership = TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'role' => $role, 'status' => 'active', 'joined_at' => now()]);
        $this->seed(AuthorizationCatalogSeeder::class);

        return $membership->fresh(['tenant']);
    }

    private function customer(TenantMembership $owner): Customer
    {
        return Customer::create(['tenant_id' => $owner->tenant_id, 'customer_number' => 'CLI-'.uniqid(), 'first_name' => 'Cliente', 'last_name' => 'Cartera', 'status' => 'active', 'created_by' => $owner->user_id]);
    }

    private function debt(TenantMembership $owner, Customer $customer): Sale
    {
        $sale = Sale::create(['tenant_id' => $owner->tenant_id, 'sale_number' => 'VEN-'.uniqid(), 'customer_id' => $customer->id, 'seller_id' => $owner->user_id, 'sale_type' => 'cash', 'status' => 'confirmed', 'total_amount' => '10.00', 'currency' => 'ARS', 'subtotal' => '10.00', 'discount_amount' => '0.00', 'down_payment_amount' => '10.00', 'financed_principal' => '0.00', 'financing_charge_amount' => '0.00', 'financed_total' => '0.00']);
        CustomerAccountEntry::create(['tenant_id' => $owner->tenant_id, 'customer_id' => $customer->id, 'sale_id' => $sale->id, 'entry_type' => 'cash', 'amount' => '10.00', 'outstanding_amount' => '10.00', 'due_date' => today(), 'status' => 'open']);

        return $sale;
    }

    public function test_assigned_collector_can_only_pull_and_collect_its_active_portfolio(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        $assigned = $this->customer($owner);
        $outside = $this->customer($owner);
        $this->debt($owner, $assigned);
        $this->debt($owner, $outside);
        app(CollectionAssignmentService::class)->assign($owner->tenant, $owner->user, $assigned, $collector);

        $token = $collector->user->createToken('portfolio')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $this->withToken($token)->getJson('/api/v1/customers')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id);
        $this->withToken($token)->getJson('/api/v1/customers/'.$assigned->id)->assertOk();
        $this->withToken($token)->getJson('/api/v1/customers/'.$outside->id)->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/customers/'.$outside->id.'/addresses')->assertForbidden();
        $pull = $this->withToken($token)->getJson('/api/v1/sync/pull?cursor=0')->assertOk();
        $pull->assertJsonCount(1, 'data.customers')->assertJsonPath('data.customers.0.id', $assigned->id);

        $this->actingAs($collector->user)->withSession(['active_tenant_id' => $owner->tenant_id])
            ->get('/admin/customers')->assertOk()
            ->assertSee($assigned->customer_number)
            ->assertDontSee($outside->customer_number);
        $this->get('/admin/customers/'.$assigned->id)->assertOk();
        $this->get('/admin/customers/'.$outside->id)->assertForbidden();
        foreach ([$assigned, $outside] as $customer) {
            try {
                app(PaymentService::class)->registerCash($owner->tenant, $collector->user, ['customer_id' => $customer->id, 'amount' => '10.00']);
                $this->fail('El cobrador no debe confirmar efectivo por el servicio administrativo.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_team_scope_uses_active_supervisor_membership_and_not_salesperson(): void
    {
        $owner = $this->member('owner');
        $supervisor = $this->member('supervisor', $owner->tenant);
        $collector = $this->member('collector', $owner->tenant);
        $outsideCollector = $this->member('collector', $owner->tenant);
        $customer = $this->customer($owner);
        $outside = $this->customer($owner);
        app(CollectionAssignmentService::class)->assign($owner->tenant, $owner->user, $customer, $collector);
        app(CollectionAssignmentService::class)->assign($owner->tenant, $owner->user, $outside, $outsideCollector);
        $team = CollectionTeam::create(['tenant_id' => $owner->tenant_id, 'name' => 'Norte', 'status' => 'active']);
        CollectionTeamMember::create(['tenant_id' => $owner->tenant_id, 'team_id' => $team->id, 'membership_id' => $supervisor->id, 'team_role' => 'supervisor', 'joined_at' => now()]);
        CollectionTeamMember::create(['tenant_id' => $owner->tenant_id, 'team_id' => $team->id, 'membership_id' => $collector->id, 'team_role' => 'collector', 'joined_at' => now()]);

        $token = $supervisor->user->createToken('team')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $this->withToken($token)->getJson('/api/v1/customers')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $customer->id);
        $this->withToken($token)->getJson('/api/v1/customers/'.$customer->id)->assertOk();
        $this->withToken($token)->getJson('/api/v1/customers/'.$outside->id)->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/sync/pull?cursor=0')->assertOk()->assertJsonPath('data.customers.0.id', $customer->id);

        CollectionTeamMember::where('membership_id', $supervisor->id)->update(['left_at' => now()]);
        $this->withToken($token)->getJson('/api/v1/customers')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($token)->getJson('/api/v1/sync/pull?cursor=0')->assertOk()->assertJsonCount(0, 'data.customers');
        CollectionTeamMember::create(['tenant_id' => $owner->tenant_id, 'team_id' => $team->id, 'membership_id' => $supervisor->id, 'team_role' => 'supervisor', 'joined_at' => now()]);

        $this->actingAs($supervisor->user)->withSession(['active_tenant_id' => $owner->tenant_id])
            ->get('/admin/customers')->assertOk()
            ->assertSee($customer->customer_number)
            ->assertDontSee($outside->customer_number);
        $this->get('/admin/customers/'.$outside->id)->assertForbidden();
    }

    public function test_own_customer_scope_is_consistent_between_web_and_api(): void
    {
        $owner = $this->member('owner');
        $seller = $this->member('seller', $owner->tenant);
        $own = $this->customer($owner);
        $outside = $this->customer($owner);
        $this->debt($seller, $own);
        $this->debt($owner, $outside);
        app(AuthorizationManagementService::class)->setOverride(
            $owner->tenant,
            $owner->user,
            $seller,
            Permission::where('key', 'customers.view')->firstOrFail(),
            'allow',
            'own',
        );

        $token = $seller->user->createToken('own-customers')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $this->withToken($token)->getJson('/api/v1/customers')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
        $this->withToken($token)->getJson('/api/v1/customers/'.$outside->id)->assertForbidden();

        $this->actingAs($seller->user)->withSession(['active_tenant_id' => $owner->tenant_id])
            ->get('/admin/customers')->assertOk()
            ->assertSee($own->customer_number)
            ->assertDontSee($outside->customer_number);
        $this->get('/admin/customers/'.$outside->id)->assertForbidden();
    }

    public function test_customer_scope_never_crosses_tenants(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        $foreignOwner = $this->member('owner');
        $foreignCustomer = $this->customer($foreignOwner);

        $token = $collector->user->createToken('tenant-isolation')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $this->withToken($token)->getJson('/api/v1/customers/'.$foreignCustomer->id)->assertNotFound();

        $this->actingAs($collector->user)->withSession(['active_tenant_id' => $owner->tenant_id])
            ->get('/admin/customers/'.$foreignCustomer->id)->assertNotFound();
    }

    public function test_sync_pull_only_includes_today_route_stops_assigned_to_the_collector(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        $customer = $this->customer($owner);
        $sale = $this->debt($owner, $customer);
        $route = CollectionRoute::create([
            'tenant_id' => $owner->tenant_id,
            'route_date' => today(),
            'collector_membership_id' => $collector->id,
            'name' => 'Ruta de prueba',
            'status' => 'published',
            'created_by_membership_id' => $owner->id,
            'published_by_membership_id' => $owner->id,
            'published_at' => now(),
            'version' => 1,
        ]);
        CollectionVisit::create([
            'tenant_id' => $owner->tenant_id,
            'route_id' => $route->id,
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'sequence' => 1,
            'status' => 'pending',
            'scheduled_amount' => '10.00',
            'obligation_snapshot' => [],
        ]);

        $token = $collector->user->createToken('route')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $this->withToken($token)->getJson('/api/v1/sync/pull?cursor=0')->assertOk()
            ->assertJsonPath('data.route_stops.0.customer_id', $customer->id)
            ->assertJsonPath('data.route_stops.0.sale_due_amount_cents', '1000')
            ->assertJsonPath('data.route.id', $route->id)
            ->assertJsonPath('data.route.status', 'published');

    }

    public function test_administrator_can_program_a_published_route_with_confirmed_sales(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        $customer = $this->customer($owner);
        $sale = $this->debt($owner, $customer);

        $route = app(CollectionRouteService::class)->program($owner->tenant, $owner->user, $collector->id, today()->toDateString(), [$sale->id]);

        $this->assertSame('published', $route->status);
        $this->assertSame($collector->id, $route->collector_membership_id);
        $this->assertDatabaseHas('collection_visits', [
            'tenant_id' => $owner->tenant_id,
            'route_id' => $route->id,
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
            'scheduled_amount' => '10.00',
        ]);
    }

    public function test_collector_can_start_a_published_route_and_sync_a_transfer_declaration(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        $customer = $this->customer($owner);
        $sale = $this->debt($owner, $customer);
        app(CollectionAssignmentService::class)->assign($owner->tenant, $owner->user, $customer, $collector);
        $route = app(CollectionRouteService::class)->program($owner->tenant, $owner->user, $collector->id, today()->toDateString(), [$sale->id]);
        $visit = $route->visits->first();

        app(CollectionWorkflowService::class)->startRoute($owner->tenant, $collector->user, $route);
        $token = $collector->user->createToken('collector-route')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $device = $this->withToken($token)->postJson('/api/v1/mobile-devices', ['device_identifier' => 'route-'.Str::uuid()])->assertOk()->json('data.id');
        $operationId = (string) Str::uuid();
        $this->withToken($token)->postJson('/api/v1/sync/push', ['device_id' => $device, 'operations' => [[
            'operation_id' => $operationId,
            'schema_version' => 1,
            'type' => 'collection_declaration',
            'recorded_at' => now()->toIso8601String(),
            'payload' => ['visit_id' => $visit->id, 'components' => [['method' => 'bank_transfer', 'amount' => '10.00', 'reference' => 'DEMO-TRANSFER-001']]],
        ]]])->assertOk()->assertJsonPath('data.operations.0.status', 'pending_review');

        $declaration = CollectionDeclaration::forTenant($owner->tenant)
            ->where('operation_id', $operationId)
            ->firstOrFail();

        $this->assertSame('pending', $declaration->status);
        $this->assertSame('recorded', $visit->fresh()->status);
        $this->assertNotSame('', app(CollectionDashboardNotifier::class)->revision($owner->tenant_id));

        $secondOperationId = (string) Str::uuid();
        $this->withToken($token)->postJson('/api/v1/sync/push', ['device_id' => $device, 'operations' => [[
            'operation_id' => $secondOperationId,
            'schema_version' => 1,
            'type' => 'collection_declaration',
            'recorded_at' => now()->addMinute()->toIso8601String(),
            'payload' => ['visit_id' => $visit->id, 'components' => [['method' => 'cash', 'amount' => '2.00']]],
        ]]])->assertOk()->assertJsonPath('data.operations.0.status', 'pending_review');

        $this->assertDatabaseCount('collection_declarations', 2);
        $this->assertDatabaseHas('collection_declarations', [
            'tenant_id' => $owner->tenant_id,
            'visit_id' => $visit->id,
            'operation_id' => $secondOperationId,
            'amount' => '2.00',
        ]);
        $this->assertSame('recorded', $visit->fresh()->status);
        $this->assertSame('paid', $visit->fresh()->outcome);

        $this->withToken($token)->getJson('/api/v1/sync/pull?cursor=0')
            ->assertOk()
            ->assertJsonPath('data.route_stops.0.status', 'recorded')
            ->assertJsonPath('data.route_stops.0.outcome', 'paid');

        $route->update(['status' => CollectionRoute::COMPLETED, 'completed_at' => now()]);
        $this->withToken($token)->postJson('/api/v1/sync/push', ['device_id' => $device, 'operations' => [[
            'operation_id' => (string) Str::uuid(),
            'schema_version' => 1,
            'type' => 'collection_declaration',
            'recorded_at' => now()->addMinutes(2)->toIso8601String(),
            'payload' => ['visit_id' => $visit->id, 'components' => [['method' => 'cash', 'amount' => '1.00']]],
        ]]])->assertOk()
            ->assertJsonPath('data.operations.0.status', 'pending_review')
            ->assertJsonPath('data.operations.0.error', 'La hoja debe estar iniciada para registrar una declaración.');

        $this->assertDatabaseCount('collection_declarations', 2);
    }

    public function test_collections_stream_requires_the_active_tenant_and_emits_an_invalidation_without_financial_data(): void
    {
        $owner = $this->member('owner');
        $revision = app(CollectionDashboardNotifier::class)->notify($owner->tenant_id);

        $response = $this->actingAs($owner->user)
            ->withSession(['active_tenant_id' => $owner->tenant_id])
            ->get('/admin/collections/stream');

        $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
        $stream = $response->streamedContent();
        $this->assertStringContainsString('event: collections.changed', $stream);
        $this->assertStringContainsString('id: '.$revision, $stream);
        $this->assertStringNotContainsString($owner->tenant_id, $stream);

        $other = $this->member('owner');
        $this->actingAs($other->user)
            ->withSession(['active_tenant_id' => $owner->tenant_id])
            ->get('/admin/collections/stream')
            ->assertForbidden();
    }

    public function test_collections_dashboard_rebuilds_the_tenant_context_on_livewire_actions(): void
    {
        $owner = $this->member('owner');
        Livewire::actingAs($owner->user);

        Livewire::test(CollectionsDashboard::class, ['tenantId' => $owner->tenant_id])
            ->call('showTab', 'routes')
            ->assertSet('activeTab', 'routes')
            ->assertSee('Hojas de cobranza');
    }

    public function test_reassignment_invalidates_cached_portfolio_and_revoked_collection_permission_preserves_sync_for_review(): void
    {
        $owner = $this->member('owner');
        $first = $this->member('collector', $owner->tenant);
        $second = $this->member('collector', $owner->tenant);
        $customer = $this->customer($owner);
        $this->debt($owner, $customer);
        $assignment = app(CollectionAssignmentService::class)->assign($owner->tenant, $owner->user, $customer, $first);
        $oldVersion = $owner->tenant->fresh()->authorization_version;
        app(CollectionAssignmentService::class)->assign($owner->tenant, $owner->user, $customer, $second);
        $this->assertNotNull($assignment->fresh()->unassigned_at);
        $this->assertGreaterThan($oldVersion, $owner->tenant->fresh()->authorization_version);

        $token = $second->user->createToken('revoked')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $device = $this->withToken($token)->postJson('/api/v1/mobile-devices', ['device_identifier' => 'portfolio-'.Str::uuid()])->assertOk()->json('data.id');
        app(AuthorizationManagementService::class)->setOverride($owner->tenant, $owner->user, $second, Permission::where('key', 'payments.create')->firstOrFail(), 'deny', null);
        $this->withToken($token)->postJson('/api/v1/sync/push', ['device_id' => $device, 'operations' => [[
            'operation_id' => (string) Str::uuid(), 'schema_version' => 1, 'type' => 'cash_payment', 'recorded_at' => now()->toIso8601String(),
            'payload' => ['customer_id' => $customer->id, 'amount' => '10.00', 'received_at' => now()->toIso8601String()],
        ]]])->assertOk()->assertJsonPath('data.operations.0.status', 'pending_review');
        $this->assertSame('10.00', CustomerAccountEntry::where('customer_id', $customer->id)->value('outstanding_amount'));
    }

    public function test_livewire_hydrates_the_signed_tenant_context_across_multiple_actions(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        $replacement = $this->member('collector', $owner->tenant);
        $customer = $this->customer($owner);
        Livewire::actingAs($owner->user);

        Livewire::test(CollectionPortfolioManager::class, ['tenantId' => $owner->tenant_id])
            ->call('beginAssignment', $customer->id)
            ->set('newCollectorId', $collector->id)
            ->set('reason', 'Cobertura norte')
            ->call('saveAssignment')
            ->assertSet('confirming', false)
            ->set('search', 'Cartera')
            ->assertSee('Cliente')
            ->call('beginAssignment', $customer->id)
            ->set('newCollectorId', $replacement->id)
            ->set('reason', 'Reasignación')
            ->call('saveAssignment')
            ->call('removeAssignment', $customer->id)
            ->assertSee('Historial de asignaciones');

        $this->assertSame(2, $customer->collectorAssignments()->count());
        $this->assertDatabaseHas('customer_collector_assignments', ['tenant_id' => $owner->tenant_id, 'customer_id' => $customer->id, 'collector_membership_id' => $collector->id, 'reason' => 'Reasignación']);
        $this->assertDatabaseMissing('customer_collector_assignments', ['tenant_id' => $owner->tenant_id, 'customer_id' => $customer->id, 'unassigned_at' => null]);
    }

    public function test_livewire_manages_zones_and_team_members_across_multiple_actions(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        $customer = $this->customer($owner);
        Livewire::actingAs($owner->user);

        $component = Livewire::test(CollectionStructuresManager::class, ['tenantId' => $owner->tenant_id])
            ->set('zoneCode', 'NORTE')
            ->set('zoneName', 'Zona Norte')
            ->call('saveZone');
        $zone = CollectionZone::forTenant($owner->tenant)->firstOrFail();
        $component->call('editZone', $zone->id)->set('zoneName', 'Zona Norte Editada')->call('saveZone')
            ->set('customerId', $customer->id)->set('customerZoneId', $zone->id)->call('assignZone')
            ->call('toggleZone', $zone->id)
            ->call('toggleZone', $zone->id)
            ->set('teamName', 'Equipo Norte')->call('saveTeam');
        $team = CollectionTeam::forTenant($owner->tenant)->firstOrFail();
        $component->call('editTeam', $team->id)->set('teamName', 'Equipo Norte Editado')->call('saveTeam')
            ->call('toggleTeam', $team->id)->call('toggleTeam', $team->id)
            ->set('teamId', $team->id)->set('membershipId', $collector->id)->set('teamRole', 'collector')->call('addMember');
        $member = CollectionTeamMember::forTenant($owner->tenant)->where('team_id', $team->id)->where('membership_id', $collector->id)->whereNull('left_at')->firstOrFail();
        $component->call('removeMember', $member->id)->call('addMember')->assertSee('Equipo Norte Editado');

        $this->assertDatabaseHas('collection_zones', ['id' => $zone->id, 'name' => 'Zona Norte Editada']);
        $this->assertDatabaseHas('customer_collection_zone_assignments', ['tenant_id' => $owner->tenant_id, 'customer_id' => $customer->id, 'zone_id' => $zone->id, 'unassigned_at' => null]);
        $this->assertSame(2, CollectionTeamMember::forTenant($owner->tenant)->where('team_id', $team->id)->where('membership_id', $collector->id)->count());
        $this->assertDatabaseHas('collection_team_members', ['tenant_id' => $owner->tenant_id, 'team_id' => $team->id, 'membership_id' => $collector->id, 'left_at' => null]);
    }

    public function test_livewire_revalidates_membership_on_a_subsequent_request(): void
    {
        $owner = $this->member('owner');
        Livewire::actingAs($owner->user);

        $component = Livewire::test(CollectionPortfolioManager::class, ['tenantId' => $owner->tenant_id]);
        $owner->update(['status' => 'suspended']);

        $component->set('search', 'Cliente')->assertForbidden();
    }

    public function test_livewire_rejects_a_tenant_not_authorized_for_the_authenticated_user(): void
    {
        $owner = $this->member('owner');
        $otherOwner = $this->member('owner');
        Livewire::actingAs($owner->user);

        Livewire::test(CollectionPortfolioManager::class, ['tenantId' => $otherOwner->tenant_id])->assertForbidden();
    }
}
