<?php

namespace Tests\Feature;

use App\Domain\Collections\CollectionAssignmentService;
use App\Domain\Collections\PaymentService;
use App\Domain\Tenancy\AuthorizationManagementService;
use App\Livewire\CollectionPortfolioManager;
use App\Livewire\CollectionStructuresManager;
use App\Models\CollectionTeam;
use App\Models\CollectionTeamMember;
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

    private function debt(TenantMembership $owner, Customer $customer): void
    {
        $sale = Sale::create(['tenant_id' => $owner->tenant_id, 'sale_number' => 'VEN-'.uniqid(), 'customer_id' => $customer->id, 'seller_id' => $owner->user_id, 'sale_type' => 'cash', 'status' => 'confirmed', 'total_amount' => '10.00', 'currency' => 'ARS', 'subtotal' => '10.00', 'discount_amount' => '0.00', 'down_payment_amount' => '10.00', 'financed_principal' => '0.00', 'financing_charge_amount' => '0.00', 'financed_total' => '0.00']);
        CustomerAccountEntry::create(['tenant_id' => $owner->tenant_id, 'customer_id' => $customer->id, 'sale_id' => $sale->id, 'entry_type' => 'cash', 'amount' => '10.00', 'outstanding_amount' => '10.00', 'due_date' => today(), 'status' => 'open']);
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
        $pull = $this->withToken($token)->getJson('/api/v1/sync/pull?cursor=0')->assertOk();
        $pull->assertJsonCount(1, 'data.customers')->assertJsonPath('data.customers.0.id', $assigned->id);
        $payment = app(PaymentService::class)->registerCash($owner->tenant, $collector->user, ['customer_id' => $assigned->id, 'amount' => '10.00']);
        $this->assertSame('confirmed', $payment->status);
        $this->expectException(HttpException::class);
        app(PaymentService::class)->registerCash($owner->tenant, $collector->user, ['customer_id' => $outside->id, 'amount' => '10.00']);
    }

    public function test_team_scope_uses_active_supervisor_membership_and_not_salesperson(): void
    {
        $owner = $this->member('owner');
        $supervisor = $this->member('supervisor', $owner->tenant);
        $collector = $this->member('collector', $owner->tenant);
        $customer = $this->customer($owner);
        app(CollectionAssignmentService::class)->assign($owner->tenant, $owner->user, $customer, $collector);
        $team = CollectionTeam::create(['tenant_id' => $owner->tenant_id, 'name' => 'Norte', 'status' => 'active']);
        CollectionTeamMember::create(['tenant_id' => $owner->tenant_id, 'team_id' => $team->id, 'membership_id' => $supervisor->id, 'team_role' => 'supervisor', 'joined_at' => now()]);
        CollectionTeamMember::create(['tenant_id' => $owner->tenant_id, 'team_id' => $team->id, 'membership_id' => $collector->id, 'team_role' => 'collector', 'joined_at' => now()]);

        $token = $supervisor->user->createToken('team')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $this->withToken($token)->getJson('/api/v1/sync/pull?cursor=0')->assertOk()->assertJsonPath('data.customers.0.id', $customer->id);
        CollectionTeamMember::where('membership_id', $supervisor->id)->update(['left_at' => now()]);
        $this->withToken($token)->getJson('/api/v1/sync/pull?cursor=0')->assertOk()->assertJsonCount(0, 'data.customers');
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
        ]]])->assertOk()->assertJsonPath('data.operations.0.status', 'under_review');
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
