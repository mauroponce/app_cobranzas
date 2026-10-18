<?php

namespace Tests\Feature;

use App\Domain\Collections\CollectionAssignmentService;
use App\Domain\Collections\CollectionRouteService;
use App\Domain\Commercial\CustomerService;
use App\Domain\Commercial\Decimal;
use App\Domain\Sales\ConfirmSaleAction;
use App\Domain\Sales\DocumentStorageService;
use App\Domain\Sales\SaleApplicationService;
use App\Livewire\CollectionsDashboard;
use App\Models\CollectionDeclaration;
use App\Models\CollectionRoute;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\AuthorizationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CollectionsEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationCatalogSeeder::class);
        Storage::fake('r2');
        Queue::fake();
    }

    private function member(string $role, ?Tenant $tenant = null, ?string $name = null): TenantMembership
    {
        $tenant ??= Tenant::create([
            'name' => 'Validación C-03',
            'slug' => 'validacion-c03-'.Str::lower(Str::random(8)),
        ]);
        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create(['name' => $name ?? ucfirst($role).' C-03'])->id,
            'role' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $this->seed(AuthorizationCatalogSeeder::class);

        return $membership->fresh(['tenant', 'user']);
    }

    private function createSale(
        TenantMembership $seller,
        TenantMembership $reviewer,
        Customer $customer,
        Product $product,
        string $amount,
        bool $financed,
    ): Sale {
        $applications = app(SaleApplicationService::class);
        $application = $applications->direct($seller->tenant, $seller->user, [
            'customer_id' => $customer->id,
            'sale_type' => $financed ? 'financed' : 'cash',
            'requested_down_payment' => $financed ? '0.00' : null,
            'requested_installment_count' => $financed ? 3 : null,
            'requested_frequency' => $financed ? 'daily' : null,
            'requested_first_due_date' => $financed ? today($seller->tenant->timezone)->toDateString() : null,
            'items' => [[
                'product_id' => $product->id,
                'description' => $product->name,
                'sku_snapshot' => $product->sku,
                'quantity' => '1.000',
                'unit_price' => $amount,
                'discount_amount' => '0.00',
                'line_total' => $amount,
            ]],
        ]);
        $applications->submit($application, $seller->user);
        $applications->assess($application, $reviewer->user, true);

        if ($financed) {
            $stored = app(DocumentStorageService::class)->store(
                $seller->tenant_id,
                $seller->user_id,
                '%PDF-1.4 synthetic C-03 identity',
                'application/pdf',
                'c03-identity.pdf',
            );
            $document = CustomerDocument::create([
                'tenant_id' => $seller->tenant_id,
                'customer_id' => $customer->id,
                'sale_application_id' => $application->id,
                'stored_document_id' => $stored->id,
                'document_type' => 'identity',
                'verification_status' => 'pending',
                'uploaded_by' => $seller->user_id,
            ]);
            $this->actingAs($reviewer->user)
                ->withSession(['active_tenant_id' => $reviewer->tenant_id])
                ->patch('/admin/customer-documents/'.$document->id.'/verify', ['status' => 'verified'])
                ->assertRedirect();
        }

        return app(ConfirmSaleAction::class)->execute($application, $reviewer->user);
    }

    private function saleBalance(Sale $sale): string
    {
        return Decimal::format(Decimal::toInteger((string) $sale->accountEntries()->sum('outstanding_amount')));
    }

    public function test_complete_collection_day_preserves_independent_sales_and_exact_balances(): void
    {
        $owner = $this->member('owner', null, 'Administración C-03');
        $admin = $this->member('admin', $owner->tenant, 'Revisión C-03');
        $collector = $this->member('collector', $owner->tenant, 'Cobrador C-03');
        $customer = app(CustomerService::class)->create($owner->tenant, $owner->user, [
            'first_name' => 'Cliente',
            'last_name' => 'Sintético C-03',
        ]);
        $product = Product::create([
            'tenant_id' => $owner->tenant_id,
            'sku' => 'C03-PRODUCT',
            'name' => 'Producto sintético C-03',
            'status' => 'active',
        ]);

        $saleA = $this->createSale($owner, $admin, $customer, $product, '300000.00', true);
        $saleB = $this->createSale($owner, $admin, $customer, $product, '60000.00', false);
        $this->assertSame(['100000.00', '100000.00', '100000.00'], $saleA->plan->installments()->pluck('amount')->all());
        $this->assertSame('300000.00', $this->saleBalance($saleA));
        $this->assertSame('60000.00', $this->saleBalance($saleB));

        app(CollectionAssignmentService::class)->assign($owner->tenant, $owner->user, $customer, $collector, 'Jornada C-03');
        $route = app(CollectionRouteService::class)->program(
            $owner->tenant,
            $owner->user,
            $collector->id,
            today($owner->tenant->timezone)->toDateString(),
            [$saleA->id],
        );
        $visit = $route->visits()->sole();

        $token = $collector->user->createToken('c03-mobile')->plainTextToken;
        auth()->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $deviceId = $this->withToken($token)->postJson('/api/v1/mobile-devices', [
            'device_identifier' => 'c03-'.Str::uuid(),
        ])->assertOk()->json('data.id');

        $this->withToken($token)->getJson('/api/v1/sync/pull?cursor=0')
            ->assertOk()
            ->assertJsonPath('data.route_stops.0.sale_id', $saleA->id)
            ->assertJsonPath('data.route_stops.0.sale_due_amount_cents', '10000000')
            ->assertJsonPath('data.route_stops.0.customer_total_outstanding_cents', '36000000');
        $this->withToken($token)->postJson('/api/v1/collection-routes/'.$route->id.'/start')->assertOk();

        $operation = [
            'operation_id' => (string) Str::uuid(),
            'schema_version' => 1,
            'type' => 'collection_declaration',
            'recorded_at' => now()->toIso8601String(),
            'payload' => [
                'visit_id' => $visit->id,
                'components' => [
                    ['method' => 'cash', 'amount' => '30000.00'],
                    ['method' => 'bank_transfer', 'amount' => '50000.00', 'reference' => 'C03-TRANSFER'],
                ],
            ],
        ];
        $firstPush = $this->withToken($token)->postJson('/api/v1/sync/push', [
            'device_id' => $deviceId,
            'operations' => [$operation],
        ])->assertOk()->assertJsonPath('data.operations.0.status', 'pending_review');
        $declarationId = $firstPush->json('data.operations.0.declaration_id');
        $this->assertSame('300000.00', $this->saleBalance($saleA));
        $this->assertSame('60000.00', $this->saleBalance($saleB));

        $this->withToken($token)->postJson('/api/v1/sync/push', [
            'device_id' => $deviceId,
            'operations' => [$operation],
        ])->assertOk()->assertJsonPath('data.operations.0.declaration_id', $declarationId);
        $this->assertDatabaseCount('collection_declarations', 1);

        $conflictingOperation = $operation;
        $conflictingOperation['payload']['components'][0]['amount'] = '30001.00';
        $this->withToken($token)->postJson('/api/v1/sync/push', [
            'device_id' => $deviceId,
            'operations' => [$conflictingOperation],
        ])->assertOk()
            ->assertJsonPath('data.operations.0.status', 'rejected')
            ->assertJsonPath('data.operations.0.error', 'El UUID fue reutilizado con otro contenido.');
        $this->assertDatabaseCount('collection_declarations', 1);

        Livewire::actingAs($admin->user);
        Livewire::test(CollectionsDashboard::class, ['tenantId' => $admin->tenant_id])
            ->assertSee('C03-TRANSFER')
            ->assertSee('$ 80.000,00')
            ->call('approve', $declarationId)
            ->assertHasNoErrors()
            ->assertSee('La transferencia fue conciliada');
        $this->assertSame('250000.00', $this->saleBalance($saleA));
        $this->assertSame('60000.00', $this->saleBalance($saleB));

        auth()->forgetGuards();
        $settlementResponse = $this->withToken($token)->postJson('/api/v1/collection-routes/'.$route->id.'/settlement', [
            'local_queue_empty' => true,
        ])->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.declared_amount', '30000.00');
        $settlementId = $settlementResponse->json('data.id');
        $cashComponentId = CollectionDeclaration::findOrFail($declarationId)->components()->where('method', 'cash')->sole()->id;

        Livewire::actingAs($admin->user);
        Livewire::test(CollectionsDashboard::class, ['tenantId' => $admin->tenant_id])
            ->call('showTab', 'settlements')
            ->call('reviewSettlement', $settlementId)
            ->assertSee('$ 30.000,00')
            ->set('settlementReceivedAmount', '25000.00')
            ->set('settlementAttributions', [$cashComponentId => '25000.00'])
            ->set('settlementNotes', 'Faltante sintético C-03')
            ->call('approveSettlement')
            ->assertHasNoErrors()
            ->assertSee('quedó registrada para revisión');

        $this->assertSame('225000.00', $this->saleBalance($saleA));
        $this->assertSame('60000.00', $this->saleBalance($saleB));
        $this->assertSame('285000.00', Decimal::format(Decimal::toInteger((string) $customer->accountEntries()->sum('outstanding_amount'))));
        $this->assertSame(CollectionRoute::COMPLETED, $route->fresh()->status);
        $this->assertDatabaseHas('cash_discrepancies', [
            'tenant_id' => $owner->tenant_id,
            'settlement_id' => $settlementId,
            'expected_amount' => '30000.00',
            'received_amount' => '25000.00',
            'reason' => 'Faltante sintético C-03',
        ]);
        $this->assertDatabaseHas('collection_incidents', [
            'tenant_id' => $owner->tenant_id,
            'route_id' => $route->id,
            'type' => 'cash_difference',
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $owner->tenant_id,
            'subject_id' => $settlementId,
            'action' => 'cash_settlement.disputed',
        ]);

        $lateOperation = $operation;
        $lateOperation['operation_id'] = (string) Str::uuid();
        $lateOperation['recorded_at'] = now()->addMinute()->toIso8601String();
        auth()->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/sync/push', [
            'device_id' => $deviceId,
            'operations' => [$lateOperation],
        ])->assertOk()
            ->assertJsonPath('data.operations.0.status', 'pending_review');
        $this->assertSame('285000.00', Decimal::format(Decimal::toInteger((string) $customer->accountEntries()->sum('outstanding_amount'))));
        $this->assertDatabaseHas('collection_incidents', [
            'tenant_id' => $owner->tenant_id,
            'visit_id' => $visit->id,
            'type' => 'financial_sync_conflict',
        ]);

        Livewire::actingAs($admin->user);
        Livewire::test(CollectionsDashboard::class, ['tenantId' => $admin->tenant_id])
            ->call('showTab', 'incidents')
            ->assertSee('Diferencia de efectivo')
            ->assertSee('Cliente')
            ->assertSee('Con diferencia');
    }
}
