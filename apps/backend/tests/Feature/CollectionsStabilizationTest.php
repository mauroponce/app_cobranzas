<?php

namespace Tests\Feature;

use App\Domain\Collections\CashSettlementService;
use App\Domain\Collections\CollectionRouteService;
use App\Domain\Collections\CollectionWorkflowService;
use App\Domain\Collections\PaymentService;
use App\Models\CollectionDeclaration;
use App\Models\CollectionRoute;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\AuthorizationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CollectionsStabilizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationCatalogSeeder::class);
    }

    private function member(string $role, ?Tenant $tenant = null): TenantMembership
    {
        $tenant ??= Tenant::create(['name' => 'Empresa '.uniqid(), 'slug' => 'empresa-'.uniqid()]);
        $membership = TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'role' => $role, 'status' => 'active', 'joined_at' => now()]);
        $this->seed(AuthorizationCatalogSeeder::class);

        return $membership->fresh(['tenant']);
    }

    /** @return array{Customer, Sale, CustomerAccountEntry} */
    private function debt(TenantMembership $owner, string $amount): array
    {
        $customer = Customer::create(['tenant_id' => $owner->tenant_id, 'customer_number' => 'CLI-'.uniqid(), 'first_name' => 'Ana', 'last_name' => 'Prueba', 'status' => 'active', 'created_by' => $owner->user_id]);
        $sale = Sale::create(['tenant_id' => $owner->tenant_id, 'sale_number' => 'VEN-'.uniqid(), 'customer_id' => $customer->id, 'seller_id' => $owner->user_id, 'sale_type' => 'cash', 'status' => 'confirmed', 'total_amount' => $amount, 'currency' => 'ARS', 'subtotal' => $amount, 'discount_amount' => '0.00', 'down_payment_amount' => $amount, 'financed_principal' => '0.00', 'financing_charge_amount' => '0.00', 'financed_total' => '0.00']);
        $entry = CustomerAccountEntry::create(['tenant_id' => $owner->tenant_id, 'customer_id' => $customer->id, 'sale_id' => $sale->id, 'entry_type' => 'cash', 'amount' => $amount, 'outstanding_amount' => $amount, 'due_date' => today(), 'status' => 'open']);

        return [$customer, $sale, $entry];
    }

    private function startedRoute(TenantMembership $owner, TenantMembership $collector, Sale $sale): CollectionRoute
    {
        $route = app(CollectionRouteService::class)->program($owner->tenant, $owner->user, $collector->id, today()->toDateString(), [$sale->id]);
        app(CollectionWorkflowService::class)->startRoute($owner->tenant, $collector->user, $route);

        return $route->fresh('visits');
    }

    private function declaration(TenantMembership $owner, TenantMembership $collector, CollectionRoute $route, array $components): CollectionDeclaration
    {
        return app(CollectionWorkflowService::class)->declare($owner->tenant, $collector->user, [
            'visit_id' => $route->visits->first()->id,
            'operation_id' => (string) Str::uuid(),
            'components' => $components,
        ]);
    }

    public function test_combined_and_multiple_declarations_only_reduce_the_sale_debt_when_each_component_is_reconciled(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [, $sale, $entry] = $this->debt($owner, '20.00');
        $route = $this->startedRoute($owner, $collector, $sale);
        $combined = $this->declaration($owner, $collector, $route, [
            ['method' => 'cash', 'amount' => '10.00'],
            ['method' => 'bank_transfer', 'amount' => '5.00', 'reference' => 'TR-1'],
        ]);
        $this->declaration($owner, $collector, $route, [['method' => 'cash', 'amount' => '3.00']]);
        $this->assertSame('20.00', $entry->fresh()->outstanding_amount);

        try {
            app(PaymentService::class)->approveDeclaration($owner->tenant, $owner->user, $combined);
            $this->fail('Una declaración combinada no debe aprobar efectivo fuera de la rendición.');
        } catch (ValidationException) {
        }
        $transfer = $combined->components()->where('method', 'bank_transfer')->firstOrFail();
        app(PaymentService::class)->confirmTransferComponent($owner->tenant, $owner->user, $transfer);
        $this->assertSame('15.00', $entry->fresh()->outstanding_amount);

        $settlements = app(CashSettlementService::class);
        $settlement = $settlements->submit($owner->tenant, $collector->user, $settlements->createForRoute($owner->tenant, $collector->user, $route, true));
        $settlements->approve($owner->tenant, $owner->user, $settlement, '13.00');
        $this->assertSame('2.00', $entry->fresh()->outstanding_amount);
        $this->assertSame(CollectionRoute::COMPLETED, $route->fresh()->status);
        $this->expectException(ValidationException::class);
        $settlements->approve($owner->tenant, $owner->user, $settlement, '13.00');
    }

    public function test_difference_requires_explicit_attribution_and_creates_a_financial_incident(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [, $sale, $entry] = $this->debt($owner, '10.00');
        $route = $this->startedRoute($owner, $collector, $sale);
        $first = $this->declaration($owner, $collector, $route, [['method' => 'cash', 'amount' => '6.00']]);
        $second = $this->declaration($owner, $collector, $route, [['method' => 'cash', 'amount' => '4.00']]);
        $settlements = app(CashSettlementService::class);
        $settlement = $settlements->submit($owner->tenant, $collector->user, $settlements->createForRoute($owner->tenant, $collector->user, $route, true));

        try {
            $settlements->approve($owner->tenant, $owner->user, $settlement, '7.00');
            $this->fail('Una diferencia no atribuida debe rechazarse.');
        } catch (ValidationException) {
        }
        $approved = $settlements->approve($owner->tenant, $owner->user, $settlement, '7.00', [
            $first->components()->firstOrFail()->id => '6.00',
            $second->components()->firstOrFail()->id => '1.00',
        ], 'Faltante físico');
        $this->assertSame('disputed', $approved->status);
        $this->assertSame('3.00', $entry->fresh()->outstanding_amount);
        $this->assertDatabaseHas('collection_incidents', ['tenant_id' => $owner->tenant_id, 'route_id' => $route->id, 'type' => 'cash_difference']);
    }

    public function test_route_cannot_be_presented_with_pending_visits_or_a_reported_local_queue(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [, $sale] = $this->debt($owner, '10.00');
        $route = $this->startedRoute($owner, $collector, $sale);
        $settlements = app(CashSettlementService::class);
        foreach ([false, true] as $localQueueEmpty) {
            try {
                $settlements->createForRoute($owner->tenant, $collector->user, $route, $localQueueEmpty);
                $this->fail('La hoja no debe presentarse mientras falten condiciones de cierre.');
            } catch (ValidationException) {
            }
        }
        $this->assertDatabaseCount('cash_settlements', 0);
    }

    public function test_collector_cannot_bypass_a_visit_with_a_direct_administrative_payment(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [$customer] = $this->debt($owner, '10.00');

        foreach (['cash', 'bank_transfer'] as $method) {
            try {
                if ($method === 'cash') {
                    app(PaymentService::class)->registerCash($owner->tenant, $collector->user, [
                        'customer_id' => $customer->id,
                        'amount' => '10.00',
                    ]);
                } else {
                    app(PaymentService::class)->declareTransfer($owner->tenant, $collector->user, [
                        'customer_id' => $customer->id,
                        'amount' => '10.00',
                    ]);
                }
                $this->fail('El cobrador no debe usar el canal administrativo directo.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $token = $collector->user->createToken('direct-payment')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        foreach (['cash', 'bank_transfer'] as $method) {
            $this->withToken($token)->postJson('/api/v1/payments', [
                'customer_id' => $customer->id,
                'amount' => '10.00',
                'method' => $method,
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_repeated_mobile_declaration_is_idempotent(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [, $sale] = $this->debt($owner, '10.00');
        $route = $this->startedRoute($owner, $collector, $sale);
        $token = $collector->user->createToken('idempotent-declaration')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $device = $this->withToken($token)->postJson('/api/v1/mobile-devices', ['device_identifier' => 'idempotent-'.Str::uuid()])->assertOk()->json('data.id');
        $operation = [
            'operation_id' => (string) Str::uuid(), 'schema_version' => 1, 'type' => 'collection_declaration',
            'recorded_at' => now()->toIso8601String(),
            'payload' => ['visit_id' => $route->visits->first()->id, 'components' => [['method' => 'cash', 'amount' => '10.00']]],
        ];

        $first = $this->withToken($token)->postJson('/api/v1/sync/push', ['device_id' => $device, 'operations' => [$operation]])
            ->assertOk()->assertJsonPath('data.operations.0.status', 'pending_review');
        $second = $this->withToken($token)->postJson('/api/v1/sync/push', ['device_id' => $device, 'operations' => [$operation]])
            ->assertOk()->assertJsonPath('data.operations.0.status', 'pending_review');

        $this->assertSame($first->json('data.operations.0.declaration_id'), $second->json('data.operations.0.declaration_id'));
        $this->assertDatabaseCount('collection_declarations', 1);
        $this->assertDatabaseCount('collection_declaration_components', 1);
    }

    public function test_authorized_reversal_restores_debt_without_deleting_financial_history(): void
    {
        $owner = $this->member('owner');
        [$customer, , $entry] = $this->debt($owner, '10.00');
        $payment = app(PaymentService::class)->registerCash($owner->tenant, $owner->user, [
            'customer_id' => $customer->id,
            'amount' => '10.00',
        ]);
        $this->assertSame('0.00', $entry->fresh()->outstanding_amount);

        $reversed = app(PaymentService::class)->reverse($owner->tenant, $owner->user, $payment, 'Corrección administrativa');

        $this->assertSame('reversed', $reversed->status);
        $this->assertSame('10.00', $entry->fresh()->outstanding_amount);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'reversed']);
        $this->assertDatabaseHas('payment_allocations', ['tenant_id' => $owner->tenant_id, 'payment_id' => $payment->id, 'amount' => '10.00']);
        $this->assertDatabaseHas('payment_reversals', ['tenant_id' => $owner->tenant_id, 'payment_id' => $payment->id, 'reason' => 'Corrección administrativa']);
    }

    public function test_overpayment_from_a_declaration_is_not_turned_into_credit_automatically(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [, $sale, $entry] = $this->debt($owner, '10.00');
        $route = $this->startedRoute($owner, $collector, $sale);
        $this->declaration($owner, $collector, $route, [['method' => 'cash', 'amount' => '15.00']]);
        $settlements = app(CashSettlementService::class);
        $settlement = $settlements->submit($owner->tenant, $collector->user, $settlements->createForRoute($owner->tenant, $collector->user, $route, true));
        $settlements->approve($owner->tenant, $owner->user, $settlement, '15.00');
        $this->assertSame('0.00', $entry->fresh()->outstanding_amount);
        $this->assertDatabaseHas('payments', ['tenant_id' => $owner->tenant_id, 'amount' => '15.00', 'unallocated_amount' => '5.00']);
        $this->assertDatabaseCount('customer_credits', 0);
        $this->assertDatabaseHas('collection_incidents', ['tenant_id' => $owner->tenant_id, 'type' => 'excess_pending_approval']);
    }

    public function test_reassignment_preserves_history_and_blocks_the_previous_collector(): void
    {
        $owner = $this->member('owner');
        $first = $this->member('collector', $owner->tenant);
        $second = $this->member('collector', $owner->tenant);
        [, $sale, $entry] = $this->debt($owner, '10.00');
        $route = $this->startedRoute($owner, $first, $sale);
        $source = $route->visits->first();
        $target = app(CollectionRouteService::class)->reassignVisit($owner->tenant, $owner->user, $source, $second->id, 'Cambio de zona');
        $this->assertSame('reassigned', $source->fresh()->status);
        $this->assertSame('pending', $target->status);
        $this->assertDatabaseHas('collection_visit_reassignments', ['tenant_id' => $owner->tenant_id, 'source_visit_id' => $source->id, 'target_visit_id' => $target->id, 'reason' => 'Cambio de zona']);

        $token = $first->user->createToken('reassigned')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $device = $this->withToken($token)->postJson('/api/v1/mobile-devices', ['device_identifier' => 'reassigned-'.Str::uuid()])->assertOk()->json('data.id');
        $this->withToken($token)->postJson('/api/v1/sync/push', ['device_id' => $device, 'operations' => [[
            'operation_id' => (string) Str::uuid(), 'schema_version' => 1, 'type' => 'collection_declaration',
            'recorded_at' => now()->toIso8601String(),
            'payload' => ['visit_id' => $source->id, 'components' => [['method' => 'cash', 'amount' => '10.00']]],
        ]]])->assertOk()->assertJsonPath('data.operations.0.status', 'pending_review');
        $this->assertSame('10.00', $entry->fresh()->outstanding_amount);
        $this->assertDatabaseHas('collection_incidents', ['tenant_id' => $owner->tenant_id, 'visit_id' => $source->id, 'type' => 'financial_sync_conflict']);
    }

    public function test_sale_balance_in_pull_is_distinct_from_the_customer_total(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [$customer, $firstSale] = $this->debt($owner, '10.00');
        $secondSale = Sale::create(['tenant_id' => $owner->tenant_id, 'sale_number' => 'VEN-'.uniqid(), 'customer_id' => $customer->id, 'seller_id' => $owner->user_id, 'sale_type' => 'cash', 'status' => 'confirmed', 'total_amount' => '20.00', 'currency' => 'ARS', 'subtotal' => '20.00', 'discount_amount' => '0.00', 'down_payment_amount' => '20.00', 'financed_principal' => '0.00', 'financing_charge_amount' => '0.00', 'financed_total' => '0.00']);
        CustomerAccountEntry::create(['tenant_id' => $owner->tenant_id, 'customer_id' => $customer->id, 'sale_id' => $secondSale->id, 'entry_type' => 'cash', 'amount' => '20.00', 'outstanding_amount' => '20.00', 'due_date' => today(), 'status' => 'open']);
        app(CollectionRouteService::class)->program($owner->tenant, $owner->user, $collector->id, today()->toDateString(), [$firstSale->id]);

        $token = $collector->user->createToken('balances')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $this->withToken($token)->getJson('/api/v1/sync/pull?cursor=0')->assertOk()
            ->assertJsonPath('data.route_stops.0.sale_due_amount_cents', '1000')
            ->assertJsonPath('data.route_stops.0.customer_total_outstanding_cents', '3000');
    }

    public function test_revoked_device_preserves_a_declaration_for_review_without_touching_debt(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [, $sale, $entry] = $this->debt($owner, '10.00');
        $route = $this->startedRoute($owner, $collector, $sale);
        $token = $collector->user->createToken('revoked-declaration')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $device = $this->withToken($token)->postJson('/api/v1/mobile-devices', ['device_identifier' => 'revoked-'.Str::uuid()])->assertOk()->json('data.id');
        DB::table('mobile_devices')->where('id', $device)->update(['revoked_at' => now()]);

        $this->withToken($token)->postJson('/api/v1/sync/push', ['device_id' => $device, 'operations' => [[
            'operation_id' => (string) Str::uuid(), 'schema_version' => 1, 'type' => 'collection_declaration',
            'recorded_at' => now()->toIso8601String(),
            'payload' => ['visit_id' => $route->visits->first()->id, 'components' => [['method' => 'cash', 'amount' => '10.00']]],
        ]]])->assertOk()->assertJsonPath('data.operations.0.status', 'pending_review')->assertJsonPath('data.operations.0.reason', 'device_revoked');
        $this->assertSame('10.00', $entry->fresh()->outstanding_amount);
        $this->assertDatabaseCount('collection_declarations', 0);
        $this->assertDatabaseHas('collection_incidents', ['tenant_id' => $owner->tenant_id, 'type' => 'device_revoked']);
    }

    public function test_foreign_tenant_cannot_approve_a_settlement(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        [, $sale] = $this->debt($owner, '1.00');
        $route = $this->startedRoute($owner, $collector, $sale);
        $this->declaration($owner, $collector, $route, [['method' => 'cash', 'amount' => '1.00']]);
        $service = app(CashSettlementService::class);
        $settlement = $service->submit($owner->tenant, $collector->user, $service->createForRoute($owner->tenant, $collector->user, $route, true));
        $foreign = $this->member('owner');

        $this->expectException(HttpException::class);
        $service->approve($foreign->tenant, $foreign->user, $settlement, '1.00');
    }
}
