<?php

namespace Tests\Feature;

use App\Domain\Collections\CashSettlementService;
use App\Domain\Collections\PaymentService;
use App\Domain\Tenancy\AuthorizationManagementService;
use App\Domain\Tenancy\AuthorizationService;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Permission;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\AuthorizationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CollectionsStageFiveTest extends TestCase
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
        $member = TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'role' => $role, 'status' => 'active', 'joined_at' => now()]);
        $this->seed(AuthorizationCatalogSeeder::class);

        return $member->fresh();
    }

    private function customer(TenantMembership $member): Customer
    {
        return Customer::create(['tenant_id' => $member->tenant_id, 'customer_number' => 'CLI-'.uniqid(), 'first_name' => 'Ana', 'last_name' => 'Pago', 'status' => 'active', 'created_by' => $member->user_id]);
    }

    private function obligation(TenantMembership $member, Customer $customer, string $amount): CustomerAccountEntry
    {
        $sale = Sale::create(['tenant_id' => $member->tenant_id, 'sale_number' => 'VEN-'.uniqid(), 'customer_id' => $customer->id, 'seller_id' => $member->user_id, 'sale_type' => 'cash', 'status' => 'confirmed', 'total_amount' => $amount, 'currency' => 'ARS', 'subtotal' => $amount, 'discount_amount' => '0.00', 'down_payment_amount' => $amount, 'financed_principal' => '0.00', 'financing_charge_amount' => '0.00', 'financed_total' => '0.00']);

        return CustomerAccountEntry::create(['tenant_id' => $member->tenant_id, 'customer_id' => $customer->id, 'sale_id' => $sale->id, 'entry_type' => 'cash', 'amount' => $amount, 'outstanding_amount' => $amount, 'due_date' => now()->toDateString(), 'status' => 'open']);
    }

    public function test_cash_payment_allocates_multiple_obligations_and_keeps_overpayment_as_credit(): void
    {
        $owner = $this->member('owner');
        $customer = $this->customer($owner);
        $first = $this->obligation($owner, $customer, '10.00');
        $second = $this->obligation($owner, $customer, '5.00');
        $payment = app(PaymentService::class)->registerCash($owner->tenant, $owner->user, ['customer_id' => $customer->id, 'amount' => '20.00']);
        $this->assertSame('confirmed', $payment->status);
        $this->assertSame('0.00', $first->fresh()->outstanding_amount);
        $this->assertSame('0.00', $second->fresh()->outstanding_amount);
        $this->assertDatabaseHas('customer_credits', ['tenant_id' => $owner->tenant_id, 'payment_id' => $payment->id, 'remaining_amount' => '5.00']);
    }

    public function test_transfer_does_not_reduce_debt_until_an_authorized_manual_confirmation(): void
    {
        $owner = $this->member('owner');
        $customer = $this->customer($owner);
        $entry = $this->obligation($owner, $customer, '10.00');
        $payment = app(PaymentService::class)->declareTransfer($owner->tenant, $owner->user, ['customer_id' => $customer->id, 'amount' => '10.00']);
        $this->assertSame('pending_verification', $payment->status);
        $this->assertSame('10.00', $entry->fresh()->outstanding_amount);
        app(PaymentService::class)->confirmTransfer($owner->tenant, $owner->user, $payment);
        $this->assertSame('0.00', $entry->fresh()->outstanding_amount);
    }

    public function test_override_deny_takes_precedence_and_feature_switch_invalidates_authorization(): void
    {
        $owner = $this->member('owner');
        $supervisor = $this->member('supervisor', $owner->tenant);
        $permission = Permission::where('key', 'payments.create')->firstOrFail();
        $service = app(AuthorizationManagementService::class);
        $service->setOverride($owner->tenant, $owner->user, $supervisor, $permission, 'deny', null);
        $this->assertFalse(app(AuthorizationService::class)->allows($supervisor->fresh()->load('tenant'), 'payments.create'));
        $feature = TenantFeature::where('tenant_id', $owner->tenant_id)->whereHas('feature', fn ($query) => $query->where('key', 'collections'))->firstOrFail();
        $service->setFeature($owner->tenant, $owner->user, $feature, false);
        $this->assertFalse(app(AuthorizationService::class)->allows($owner->fresh()->load('tenant'), 'payments.view'));
    }

    public function test_settlement_cannot_be_approved_by_presenter_and_records_a_difference(): void
    {
        $owner = $this->member('owner');
        $collector = $this->member('collector', $owner->tenant);
        $customer = $this->customer($owner);
        $this->obligation($owner, $customer, '10.00');
        $payment = app(PaymentService::class)->registerCash($owner->tenant, $collector->user, ['customer_id' => $customer->id, 'amount' => '10.00']);
        $settlements = app(CashSettlementService::class);
        $settlement = $settlements->create($owner->tenant, $collector->user, [['payment_id' => $payment->id, 'amount' => '10.00']]);
        $settlement = $settlements->submit($owner->tenant, $collector->user, $settlement);
        app(AuthorizationManagementService::class)->setOverride($owner->tenant, $owner->user, $collector, Permission::where('key', 'settlements.approve')->firstOrFail(), 'allow', 'all');
        try {
            $settlements->approve($owner->tenant, $collector->user, $settlement, '10.00');
            $this->fail('Expected self approval to fail.');
        } catch (ValidationException) {
        }
        $this->assertTrue(app(AuthorizationService::class)->allows($owner->fresh()->load('tenant'), 'settlements.approve'));
        $approved = $settlements->approve($owner->tenant, $owner->user, $settlement, '9.00', 'Faltante declarado');
        $this->assertSame('disputed', $approved->status);
        $this->assertDatabaseHas('cash_discrepancies', ['tenant_id' => $owner->tenant_id, 'settlement_id' => $approved->id, 'declared_amount' => '9.00']);
    }
}
