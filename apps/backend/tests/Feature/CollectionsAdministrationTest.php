<?php

namespace Tests\Feature;

use App\Domain\Collections\CashSettlementService;
use App\Domain\Collections\CollectionRouteService;
use App\Domain\Collections\CollectionWorkflowService;
use App\Domain\Tenancy\AuthorizationManagementService;
use App\Livewire\CollectionsDashboard;
use App\Models\CashSettlement;
use App\Models\CollectionDeclaration;
use App\Models\CollectionRoute;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Permission;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\AuthorizationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CollectionsAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationCatalogSeeder::class);
    }

    private function member(string $role, ?Tenant $tenant = null, ?string $name = null): TenantMembership
    {
        $tenant ??= Tenant::create(['name' => 'Administración '.uniqid(), 'slug' => 'administracion-'.uniqid()]);
        $user = User::factory()->create(['name' => $name ?? ucfirst($role).' '.uniqid()]);
        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $this->seed(AuthorizationCatalogSeeder::class);

        return $membership->fresh(['tenant', 'user']);
    }

    /** @return array{Customer, Sale, CustomerAccountEntry} */
    private function debt(TenantMembership $owner, string $amount = '10.00', ?string $lastName = null): array
    {
        $customer = Customer::create([
            'tenant_id' => $owner->tenant_id,
            'customer_number' => 'CLI-'.uniqid(),
            'first_name' => 'Ana',
            'last_name' => $lastName ?? 'Prueba',
            'status' => 'active',
            'created_by' => $owner->user_id,
        ]);
        $sale = Sale::create([
            'tenant_id' => $owner->tenant_id,
            'sale_number' => 'VEN-'.uniqid(),
            'customer_id' => $customer->id,
            'seller_id' => $owner->user_id,
            'sale_type' => 'cash',
            'status' => 'confirmed',
            'total_amount' => $amount,
            'currency' => 'ARS',
            'subtotal' => $amount,
            'discount_amount' => '0.00',
            'down_payment_amount' => $amount,
            'financed_principal' => '0.00',
            'financing_charge_amount' => '0.00',
            'financed_total' => '0.00',
        ]);
        $entry = CustomerAccountEntry::create([
            'tenant_id' => $owner->tenant_id,
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'entry_type' => 'cash',
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'due_date' => today(),
            'status' => 'open',
        ]);

        return [$customer, $sale, $entry];
    }

    private function route(TenantMembership $owner, TenantMembership $collector, Sale $sale, bool $started = false): CollectionRoute
    {
        $route = app(CollectionRouteService::class)->program($owner->tenant, $owner->user, $collector->id, today()->toDateString(), [$sale->id]);
        if ($started) {
            app(CollectionWorkflowService::class)->startRoute($owner->tenant, $collector->user, $route);
        }

        return $route->fresh('visits');
    }

    private function declaration(TenantMembership $owner, TenantMembership $collector, CollectionRoute $route, string $amount): CollectionDeclaration
    {
        return app(CollectionWorkflowService::class)->declare($owner->tenant, $collector->user, [
            'visit_id' => $route->visits->first()->id,
            'operation_id' => (string) Str::uuid(),
            'components' => [['method' => 'cash', 'amount' => $amount]],
        ]);
    }

    /** @return array{CashSettlement, CollectionDeclaration, CustomerAccountEntry, CollectionRoute} */
    private function submittedSettlement(TenantMembership $owner, TenantMembership $collector, string $amount = '10.00', ?string $lastName = null): array
    {
        [, $sale, $entry] = $this->debt($owner, $amount, $lastName);
        $route = $this->route($owner, $collector, $sale, true);
        $declaration = $this->declaration($owner, $collector, $route, $amount);
        $service = app(CashSettlementService::class);
        $settlement = $service->submit($owner->tenant, $collector->user, $service->createForRoute($owner->tenant, $collector->user, $route, true));

        return [$settlement, $declaration, $entry, $route];
    }

    private function dashboard(TenantMembership $membership): Testable
    {
        Livewire::actingAs($membership->user);

        return Livewire::test(CollectionsDashboard::class, ['tenantId' => $membership->tenant_id]);
    }

    public function test_settlement_list_and_detail_are_scoped_to_the_active_tenant(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant, 'Cobrador Local');
        [$settlement] = $this->submittedSettlement($owner, $collector, '10.00', 'Local');
        $foreignOwner = $this->member('owner');
        $foreignCollector = $this->member('collector', $foreignOwner->tenant, 'Cobrador Ajeno');
        [$foreignSettlement] = $this->submittedSettlement($foreignOwner, $foreignCollector, '20.00', 'Ajena');

        $component = $this->dashboard($owner)
            ->call('showTab', 'settlements')
            ->assertSee('Cobrador Local')
            ->assertDontSee('Cobrador Ajeno')
            ->call('reviewSettlement', $settlement->id)
            ->assertSee('Local')
            ->assertSee('$ 10,00');

        $component->call('reviewSettlement', $foreignSettlement->id)->assertNotFound();
    }

    public function test_transfer_action_confirms_only_the_transfer_component_of_a_combined_declaration(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [, $sale, $entry] = $this->debt($owner);
        $route = $this->route($owner, $collector, $sale, true);
        $declaration = app(CollectionWorkflowService::class)->declare($owner->tenant, $collector->user, [
            'visit_id' => $route->visits->first()->id,
            'operation_id' => (string) Str::uuid(),
            'components' => [
                ['method' => 'cash', 'amount' => '5.00'],
                ['method' => 'bank_transfer', 'amount' => '5.00', 'reference' => 'TR-COMBINADA'],
            ],
        ]);

        $this->dashboard($owner)
            ->assertSee('TR-COMBINADA')
            ->call('approve', $declaration->id)
            ->assertHasNoErrors()
            ->assertSee('La transferencia fue conciliada');

        $this->assertDatabaseHas('collection_declaration_components', ['declaration_id' => $declaration->id, 'method' => 'bank_transfer', 'status' => 'confirmed', 'confirmed_amount' => '5.00']);
        $this->assertDatabaseHas('collection_declaration_components', ['declaration_id' => $declaration->id, 'method' => 'cash', 'status' => 'pending', 'confirmed_amount' => null]);
        $this->assertSame('pending', $declaration->fresh()->status);
        $this->assertSame('5.00', $entry->fresh()->outstanding_amount);
    }

    public function test_authorized_user_approves_a_settlement_without_difference_and_cannot_approve_it_twice(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [$settlement, , $entry, $route] = $this->submittedSettlement($owner, $collector);

        $component = $this->dashboard($owner)
            ->call('showTab', 'settlements')
            ->call('reviewSettlement', $settlement->id)
            ->set('settlementReceivedAmount', '10,00')
            ->call('approveSettlement')
            ->assertHasNoErrors()
            ->assertSee('La rendición fue aprobada sin diferencias.');

        $this->assertSame('approved', $settlement->fresh()->status);
        $this->assertSame('0.00', $entry->fresh()->outstanding_amount);
        $this->assertSame(CollectionRoute::COMPLETED, $route->fresh()->status);
        $this->assertDatabaseHas('audit_events', ['tenant_id' => $owner->tenant_id, 'subject_id' => $settlement->id, 'action' => 'cash_settlement.approved']);

        $component->call('reviewSettlement', $settlement->id)
            ->call('approveSettlement')
            ->assertHasErrors('selectedSettlementId');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_difference_requires_explicit_exact_attribution_without_premature_financial_changes(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [$settlement, $declaration, $entry] = $this->submittedSettlement($owner, $collector);
        $componentId = $declaration->components()->firstOrFail()->id;
        $component = $this->dashboard($owner)
            ->call('showTab', 'settlements')
            ->call('reviewSettlement', $settlement->id)
            ->set('settlementReceivedAmount', '7.00')
            ->set('settlementAttributions', [])
            ->call('approveSettlement')
            ->assertHasErrors('settlementAttributions');

        $this->assertSame('10.00', $entry->fresh()->outstanding_amount);
        $this->assertDatabaseCount('payments', 0);

        $component->set('settlementAttributions', [$componentId => '6.00'])
            ->call('approveSettlement')
            ->assertHasErrors('settlementAttributions');
        $component->set('settlementAttributions', [$componentId => '11.00'])
            ->call('approveSettlement')
            ->assertHasErrors('settlementAttributions');

        $this->assertSame('10.00', $entry->fresh()->outstanding_amount);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('submitted', $settlement->fresh()->status);
    }

    public function test_difference_with_valid_attribution_records_payment_discrepancy_incident_and_audit(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [$settlement, $declaration, $entry, $route] = $this->submittedSettlement($owner, $collector);
        $componentId = $declaration->components()->firstOrFail()->id;

        $this->dashboard($owner)
            ->call('showTab', 'settlements')
            ->call('reviewSettlement', $settlement->id)
            ->set('settlementReceivedAmount', '7.00')
            ->set('settlementAttributions', [$componentId => '7.00'])
            ->set('settlementNotes', 'Faltante físico comprobado')
            ->call('approveSettlement')
            ->assertHasNoErrors()
            ->assertSee('quedó registrada para revisión');

        $this->assertSame('disputed', $settlement->fresh()->status);
        $this->assertSame('3.00', $entry->fresh()->outstanding_amount);
        $this->assertDatabaseHas('cash_discrepancies', ['tenant_id' => $owner->tenant_id, 'settlement_id' => $settlement->id, 'received_amount' => '7.00', 'reason' => 'Faltante físico comprobado']);
        $this->assertDatabaseHas('collection_incidents', ['tenant_id' => $owner->tenant_id, 'route_id' => $route->id, 'type' => 'cash_difference']);
        $this->assertDatabaseHas('audit_events', ['tenant_id' => $owner->tenant_id, 'subject_id' => $settlement->id, 'action' => 'cash_settlement.disputed']);
    }

    public function test_received_amount_rejects_negative_invalid_and_excess_values(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [$settlement, , $entry] = $this->submittedSettlement($owner, $collector);
        $component = $this->dashboard($owner)->call('reviewSettlement', $settlement->id);

        foreach (['-1.00', 'texto', '1234567890123.00'] as $invalid) {
            $component->set('settlementReceivedAmount', $invalid)->call('approveSettlement')->assertHasErrors('settlementReceivedAmount');
        }
        $component->set('settlementReceivedAmount', '11.00')->call('approveSettlement')->assertHasErrors('settlementReceivedAmount');

        $this->assertSame('10.00', $entry->fresh()->outstanding_amount);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_presenter_cannot_approve_own_settlement_even_with_the_permission(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [$settlement, , $entry] = $this->submittedSettlement($owner, $collector);
        app(AuthorizationManagementService::class)->setOverride(
            $owner->tenant,
            $owner->user,
            $collector,
            Permission::where('key', 'settlements.approve')->firstOrFail(),
            'allow',
            'all',
        );

        $this->dashboard($collector)
            ->call('showTab', 'settlements')
            ->call('reviewSettlement', $settlement->id)
            ->call('approveSettlement')
            ->assertHasErrors('selectedSettlementId');

        $this->assertSame('submitted', $settlement->fresh()->status);
        $this->assertSame('10.00', $entry->fresh()->outstanding_amount);
    }

    public function test_user_without_approval_or_reassignment_permission_cannot_execute_actions(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        $otherCollector = $this->member('collector', $owner->tenant);
        [$settlement] = $this->submittedSettlement($owner, $collector);
        [, $sale] = $this->debt($owner, '5.00', 'Pendiente');
        $route = $this->route($owner, $collector, $sale);

        $component = $this->dashboard($collector)
            ->call('showTab', 'settlements')
            ->call('reviewSettlement', $settlement->id)
            ->assertDontSee('Confirmar recepción');
        $component->call('approveSettlement')->assertForbidden();

        $this->dashboard($collector)
            ->call('showTab', 'routes')
            ->call('showRoute', $route->id)
            ->assertDontSee('Confirmar reasignación')
            ->call('beginReassignment', $route->visits->first()->id)
            ->assertForbidden();

        $this->assertDatabaseMissing('collection_visit_reassignments', ['to_collector_membership_id' => $otherCollector->id]);
    }

    public function test_route_detail_shows_visits_current_balance_and_only_pending_reassignment_actions(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant, 'Cobrador Norte');
        [, $sale] = $this->debt($owner, '12.34', 'Saldo');
        $route = $this->route($owner, $collector, $sale);

        $this->dashboard($owner)
            ->call('showTab', 'routes')
            ->call('showRoute', $route->id)
            ->assertSee('Cobrador Norte')
            ->assertSee('Saldo')
            ->assertSee('saldo actual $ 12,34')
            ->assertSee('Reasignar');

        $route->visits->first()->update(['status' => 'recorded', 'outcome' => 'no_payment', 'recorded_at' => now()]);
        $this->dashboard($owner)->call('showTab', 'routes')->call('showRoute', $route->id)->assertDontSee('Reasignar');
    }

    public function test_reassignment_validates_same_and_foreign_collectors(): void
    {
        $owner = $this->member('owner');
        $sourceCollector = $this->member('collector', $owner->tenant);
        $foreignCollector = $this->member('collector');
        [, $sale] = $this->debt($owner);
        $route = $this->route($owner, $sourceCollector, $sale);
        $visit = $route->visits->first();

        $component = $this->dashboard($owner)
            ->call('showRoute', $route->id)
            ->call('beginReassignment', $visit->id)
            ->set('destinationCollectorMembershipId', $sourceCollector->id)
            ->set('reassignmentReason', 'Cambio operativo')
            ->call('reassignVisit')
            ->assertHasErrors('destinationCollectorMembershipId');
        $this->assertDatabaseCount('collection_visit_reassignments', 0);

        $component->set('destinationCollectorMembershipId', $foreignCollector->id)
            ->call('reassignVisit')
            ->assertNotFound();
        $this->assertSame('pending', $visit->fresh()->status);
    }

    public function test_valid_reassignment_preserves_history_audit_and_destination_context(): void
    {
        $owner = $this->member('owner');
        $sourceCollector = $this->member('collector', $owner->tenant, 'Origen');
        $targetCollector = $this->member('collector', $owner->tenant, 'Destino');
        [, $sale] = $this->debt($owner, '10.00', 'Reasignada');
        $route = $this->route($owner, $sourceCollector, $sale);
        $sourceVisit = $route->visits->first();

        $this->dashboard($owner)
            ->call('showTab', 'routes')
            ->call('showRoute', $route->id)
            ->call('beginReassignment', $sourceVisit->id)
            ->set('destinationCollectorMembershipId', $targetCollector->id)
            ->set('reassignmentReason', 'Cobertura de zona')
            ->call('reassignVisit')
            ->assertHasNoErrors()
            ->assertSee('Cobertura de zona')
            ->assertSee('Origen')
            ->assertSee('Destino');

        $history = DB::table('collection_visit_reassignments')->where('source_visit_id', $sourceVisit->id)->first();
        $this->assertNotNull($history);
        $this->assertSame('reassigned', $sourceVisit->fresh()->status);
        $this->assertDatabaseHas('collection_visits', ['id' => $history->target_visit_id, 'route_id' => $history->to_route_id, 'status' => 'pending']);
        $this->assertDatabaseHas('audit_events', ['tenant_id' => $owner->tenant_id, 'subject_id' => $history->target_visit_id, 'action' => 'collection_visit.reassigned']);
    }

    public function test_stale_visit_state_is_revalidated_before_reassignment(): void
    {
        $owner = $this->member('owner');
        $sourceCollector = $this->member('collector', $owner->tenant);
        $targetCollector = $this->member('collector', $owner->tenant);
        [, $sale] = $this->debt($owner);
        $route = $this->route($owner, $sourceCollector, $sale);
        $visit = $route->visits->first();
        $component = $this->dashboard($owner)
            ->call('showRoute', $route->id)
            ->call('beginReassignment', $visit->id)
            ->set('destinationCollectorMembershipId', $targetCollector->id)
            ->set('reassignmentReason', 'Cambio simultáneo');

        $visit->update(['status' => 'recorded', 'outcome' => 'no_payment', 'recorded_at' => now()]);
        $component->call('reassignVisit')->assertHasErrors('selectedVisitId');

        $this->assertDatabaseCount('collection_visit_reassignments', 0);
        $this->assertSame('recorded', $visit->fresh()->status);
    }

    public function test_incidents_are_read_only_and_show_related_operational_context(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant, 'Cobrador Evidencia');
        [$settlement, $declaration, , $route] = $this->submittedSettlement($owner, $collector, '10.00', 'Incidente');
        app(CashSettlementService::class)->approve($owner->tenant, $owner->user, $settlement, '7.00', [
            $declaration->components()->firstOrFail()->id => '7.00',
        ], 'Faltante documentado');

        $this->dashboard($owner)
            ->call('showTab', 'incidents')
            ->assertSee('Diferencia de efectivo')
            ->assertSee('Incidente')
            ->assertSee($route->name)
            ->assertSee('Cobrador Evidencia')
            ->assertSee('Con diferencia')
            ->assertDontSee('Resolver incidencia');
    }
}
