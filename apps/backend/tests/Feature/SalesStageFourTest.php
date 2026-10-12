<?php

namespace Tests\Feature;

use App\Domain\Commercial\CustomerService;
use App\Domain\Commercial\Decimal;
use App\Domain\Commercial\QuotationService;
use App\Domain\Sales\AccountStatementService;
use App\Domain\Sales\CancelSaleAction;
use App\Domain\Sales\ConfirmSaleAction;
use App\Domain\Sales\DocumentStorageService;
use App\Domain\Sales\GenerateSaleDocuments;
use App\Domain\Sales\InstallmentSchedule;
use App\Domain\Sales\LateFeePolicyService;
use App\Domain\Sales\SaleApplicationService;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerDocument;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Quotation;
use App\Models\SaleApplication;
use App\Models\SaleDocument;
use App\Models\StoredDocument;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesStageFourTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role, ?Tenant $tenant = null): TenantMembership
    {
        $tenant ??= Tenant::create(['name' => 'Empresa '.uniqid(), 'slug' => 'empresa-'.uniqid()]);
        $user = User::factory()->create();

        return TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active', 'joined_at' => now()]);
    }

    private function acceptedQuote(bool $financed = true): array
    {
        $owner = $this->member('owner');
        $seller = $this->member('seller', $owner->tenant);
        $customer = app(CustomerService::class)->create($owner->tenant, $seller->user, ['first_name' => 'Ana', 'last_name' => 'Demo']);
        $list = PriceList::create(['tenant_id' => $owner->tenant_id, 'name' => 'Pública', 'code' => 'PUBLIC', 'type' => 'public', 'is_default' => true, 'is_active' => true]);
        $product = Product::create(['tenant_id' => $owner->tenant_id, 'sku' => 'SKU-1', 'name' => 'Producto', 'status' => 'active']);
        ProductPrice::create(['tenant_id' => $owner->tenant_id, 'price_list_id' => $list->id, 'product_id' => $product->id, 'amount' => '10.01']);
        $service = app(QuotationService::class);
        $quote = $service->create($owner->tenant, $seller->user, ['customer_id' => $customer->id, 'price_list_id' => $list->id]);
        $service->updateDraft($quote, $seller->user, ['sale_modality' => $financed ? 'financed' : 'cash', 'down_payment_amount' => $financed ? '1.00' : null,
            'installment_count' => $financed ? 3 : null, 'installment_frequency' => $financed ? 'monthly' : null,
            'items' => [['product_id' => $product->id, 'quantity' => '1.500']]]);
        foreach ([['pending_review', $seller], ['approved', $owner], ['sent', $seller], ['accepted', $seller]] as [$state, $member]) {
            $service->transition($quote, $member->user, $state);
        }

        return [$owner, $seller, $customer, $quote->fresh()];
    }

    private function approvedApplication(bool $financed = true): array
    {
        [$owner, $seller, $customer, $quote] = $this->acceptedQuote($financed);
        $service = app(SaleApplicationService::class);
        $application = $service->fromQuotation($quote, $seller->user);
        if ($financed) {
            $application->update(['requested_first_due_date' => CarbonImmutable::today()->addDay()->toDateString()]);
        }
        $service->submit($application, $seller->user);
        $service->assess($application, $owner->user, true, $financed ? ['financing_charge' => '0.01'] : []);

        return [$owner, $seller, $customer, $application->fresh()];
    }

    public function test_schedule_is_exact_and_keeps_end_of_month(): void
    {
        $rows = app(InstallmentSchedule::class)->generate(1403, 3, 'monthly', CarbonImmutable::parse('2028-01-31'));
        $this->assertSame([467, 467, 469], array_column($rows, 'amount_cents'));
        $this->assertSame(['2028-01-31', '2028-02-29', '2028-03-31'], array_column($rows, 'due_date'));
        foreach (['daily' => '2028-02-01', 'weekly' => '2028-02-07', 'biweekly' => '2028-02-14'] as $frequency => $second) {
            $this->assertSame($second, app(InstallmentSchedule::class)->generate(200, 2, $frequency, CarbonImmutable::parse('2028-01-31'))[1]['due_date']);
        }
    }

    public function test_financed_sale_requires_document_and_creates_exact_obligations_once(): void
    {
        Storage::fake('r2');
        Queue::fake();
        [$owner, $seller, $customer, $application] = $this->approvedApplication();
        try {
            app(ConfirmSaleAction::class)->execute($application, $owner->user);
            $this->fail('A document must be verified.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('documents', $error->errors());
        }
        $stored = app(DocumentStorageService::class)->store($owner->tenant_id, $seller->user_id, '%PDF-1.4 test', 'application/pdf', 'identity.pdf');
        $document = CustomerDocument::create(['tenant_id' => $owner->tenant_id, 'customer_id' => $customer->id, 'sale_application_id' => $application->id,
            'stored_document_id' => $stored->id, 'document_type' => 'identity', 'verification_status' => 'pending', 'uploaded_by' => $seller->user_id]);
        $this->actingAs($owner->user)->withSession(['active_tenant_id' => $owner->tenant_id])->patch('/admin/customer-documents/'.$document->id.'/verify', ['status' => 'verified'])->assertRedirect();
        $sale = app(ConfirmSaleAction::class)->execute($application, $owner->user);
        $this->assertSame('15.02', $sale->total_amount);
        $this->assertSame('14.02', $sale->financed_principal);
        $this->assertSame('14.03', $sale->financed_total);
        $this->assertSame(1403, $sale->plan->installments->sum(fn ($row) => Decimal::toInteger($row->amount)));
        $this->assertSame(4, $sale->accountEntries()->count());
        $this->assertSame('15.03', app(AccountStatementService::class)->balances($customer, CarbonImmutable::today()->addYear())['contractual']);
        $this->assertDatabaseHas('customer_account_entries', ['sale_id' => $sale->id, 'entry_type' => 'down_payment', 'amount' => '1.00']);
        $this->expectException(ValidationException::class);
        app(ConfirmSaleAction::class)->execute($application, $owner->user);
    }

    public function test_cash_sale_has_one_pending_obligation_and_can_be_cancelled(): void
    {
        Queue::fake();
        [$owner, , $customer, $application] = $this->approvedApplication(false);
        $sale = app(ConfirmSaleAction::class)->execute($application, $owner->user);
        $this->assertSame('15.02', $sale->accountEntries()->sole()->outstanding_amount);
        $this->assertNull($sale->plan);
        $this->assertSame('15.02', app(AccountStatementService::class)->balances($customer, CarbonImmutable::today())['due']);
        app(CancelSaleAction::class)->execute($sale, $owner->user, 'Error comercial');
        $this->assertSame('0.00', app(AccountStatementService::class)->balances($customer, CarbonImmutable::today())['contractual']);
        $this->assertSame('cancelled', $sale->fresh()->status);
    }

    public function test_other_tenant_and_seller_cannot_approve_or_read_sale(): void
    {
        Queue::fake();
        [$owner, $seller, , $application] = $this->approvedApplication(false);
        $foreign = $this->member('owner');
        $this->actingAs($foreign->user)->withSession(['active_tenant_id' => $foreign->tenant_id])->post('/admin/sale-applications/'.$application->id.'/confirm')->assertNotFound();
        $this->actingAs($seller->user)->withSession(['active_tenant_id' => $seller->tenant_id])->post('/admin/sale-applications/'.$application->id.'/confirm')->assertForbidden();
        $sale = app(ConfirmSaleAction::class)->execute($application, $owner->user);
        $this->actingAs($foreign->user)->withSession(['active_tenant_id' => $foreign->tenant_id])->get('/admin/sales/'.$sale->id)->assertNotFound();
    }

    public function test_r2_documents_and_idempotent_pdf_job(): void
    {
        Storage::fake('r2');
        Queue::fake();
        [$owner, , , $application] = $this->approvedApplication(false);
        $sale = app(ConfirmSaleAction::class)->execute($application, $owner->user);
        $job = new GenerateSaleDocuments($owner->tenant_id, $sale->id);
        $job->handle(app(DocumentStorageService::class));
        $job->handle(app(DocumentStorageService::class));
        $this->assertDatabaseCount('sale_documents', 3);
        foreach ($sale->fresh()->accountEntries as $entry) {
            $this->assertSame('open', $entry->status);
        }
        $document = $sale->fresh()->accountEntries()->first();
        $this->assertNotNull($document);
        $stored = StoredDocument::forTenant($owner->tenant_id)->firstOrFail();
        Storage::disk('r2')->assertExists($stored->object_key);
        $this->assertStringStartsWith('%PDF', app(DocumentStorageService::class)->download($stored));
        $this->actingAs($owner->user)->withSession(['active_tenant_id' => $owner->tenant_id]);
        $this->get('/admin/sale-applications/'.$application->id)->assertOk();
        $this->get('/admin/sales/'.$sale->id)->assertOk();
        $this->get('/admin/customers/'.$sale->customer_id.'/account')->assertOk();
        $saleDocument = SaleDocument::forTenant($owner->tenant_id)->where('sale_id', $sale->id)->firstOrFail();
        $this->get('/admin/sale-documents/'.$saleDocument->id.'/download')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_late_fee_policy_is_versioned_and_explicit(): void
    {
        [$owner, , $customer, $application] = $this->approvedApplication(false);
        Queue::fake();
        $sale = app(ConfirmSaleAction::class)->execute($application, $owner->user);
        $entry = $sale->accountEntries()->sole();
        $entry->update(['due_date' => CarbonImmutable::today()->subDays(3)]);
        $statement = app(AccountStatementService::class);
        $this->assertSame('0.00', $statement->lateFee($entry->fresh(), CarbonImmutable::today()));
        app(LateFeePolicyService::class)->create($owner->tenant, $owner->user, ['effective_from' => CarbonImmutable::today()->subMonth()->toDateString(),
            'grace_days' => 1, 'daily_basis_points' => 100, 'cap_basis_points' => 500]);
        $this->assertSame('0.30', $statement->lateFee($entry->fresh(), CarbonImmutable::today()));
    }

    public function test_direct_request_is_restricted_and_foreign_product_is_rejected(): void
    {
        [$owner, $seller, $customer] = $this->acceptedQuote(false);
        $product = Product::forTenant($owner->tenant_id)->firstOrFail();
        $payload = ['customer_id' => $customer->id, 'sale_type' => 'cash', 'items' => [['product_id' => $product->id, 'quantity' => '1.000', 'unit_price' => '2.50']]];
        $this->actingAs($seller->user)->withSession(['active_tenant_id' => $seller->tenant_id])->post('/admin/sale-applications/direct', $payload)->assertForbidden();
        $foreign = $this->member('owner');
        $foreignProduct = Product::create(['tenant_id' => $foreign->tenant_id, 'sku' => 'OTHER', 'name' => 'Ajeno', 'status' => 'active']);
        $this->actingAs($owner->user)->withSession(['active_tenant_id' => $owner->tenant_id])->post('/admin/sale-applications/direct', array_replace_recursive($payload, ['items' => [['product_id' => $foreignProduct->id]]]))->assertNotFound();
        $this->post('/admin/sale-applications/direct', $payload)->assertRedirect();
        $this->assertDatabaseHas('sale_applications', ['tenant_id' => $owner->tenant_id, 'requested_total' => '2.50', 'quotation_id' => null]);
    }

    public function test_customer_document_upload_and_private_download_are_tenant_scoped(): void
    {
        Storage::fake('r2');
        [$owner, $seller, $customer] = $this->acceptedQuote(false);
        $this->actingAs($seller->user)->withSession(['active_tenant_id' => $seller->tenant_id])->post('/admin/customers/'.$customer->id.'/documents', [
            'document_type' => 'identity', 'file' => UploadedFile::fake()->createWithContent('identity.pdf', '%PDF-1.4 sample document'),
        ])->assertRedirect();
        $document = CustomerDocument::forTenant($owner->tenant_id)->firstOrFail();
        $this->get('/admin/customer-documents/'.$document->id.'/download')->assertOk();
        $this->patch('/admin/customer-documents/'.$document->id.'/verify', ['status' => 'verified'])->assertForbidden();
        $this->actingAs($owner->user)->withSession(['active_tenant_id' => $owner->tenant_id])->patch('/admin/customer-documents/'.$document->id.'/verify', ['status' => 'verified'])->assertRedirect();
        $foreign = $this->member('owner');
        $this->actingAs($foreign->user)->withSession(['active_tenant_id' => $foreign->tenant_id])->get('/admin/customer-documents/'.$document->id.'/download')->assertNotFound();
    }

    public function test_multiple_sales_are_independent_and_account_api_is_scoped(): void
    {
        Queue::fake();
        [$owner, $seller, $customer, $first] = $this->approvedApplication(false);
        $firstSale = app(ConfirmSaleAction::class)->execute($first, $owner->user);
        $quote = Quotation::forTenant($owner->tenant_id)->firstOrFail();
        $secondQuote = app(QuotationService::class)->duplicate($quote, $seller->user);
        app(QuotationService::class)->transition($secondQuote, $seller->user, 'pending_review');
        app(QuotationService::class)->transition($secondQuote, $owner->user, 'approved');
        app(QuotationService::class)->transition($secondQuote, $seller->user, 'sent');
        app(QuotationService::class)->transition($secondQuote, $seller->user, 'accepted');
        $application = app(SaleApplicationService::class)->fromQuotation($secondQuote, $seller->user);
        app(SaleApplicationService::class)->submit($application, $seller->user);
        app(SaleApplicationService::class)->assess($application, $owner->user, true);
        $secondSale = app(ConfirmSaleAction::class)->execute($application, $owner->user);
        $this->assertNotSame($firstSale->id, $secondSale->id);
        $this->assertSame('30.04', app(AccountStatementService::class)->balances($customer, CarbonImmutable::today())['due']);
        $token = $owner->user->createToken('sales-test')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $this->getJson('/api/v1/customers/'.$customer->id.'/account')->assertOk()->assertJsonPath('data.balances.due', '30.04');
        $foreign = $this->member('owner');
        auth()->forgetGuards();
        $this->withToken($foreign->user->createToken('foreign')->plainTextToken)->postJson('/api/v1/tenant/select', ['tenant_id' => $foreign->tenant_id])->assertOk();
        $this->getJson('/api/v1/customers/'.$customer->id.'/account')->assertNotFound();
    }

    public function test_database_rejects_account_entry_with_another_customer(): void
    {
        Queue::fake();
        [$owner, , , $application] = $this->approvedApplication(false);
        $sale = app(ConfirmSaleAction::class)->execute($application, $owner->user);
        $other = app(CustomerService::class)->create($owner->tenant, $owner->user, ['first_name' => 'Otra', 'last_name' => 'Persona']);
        $this->expectException(QueryException::class);
        CustomerAccountEntry::create(['tenant_id' => $owner->tenant_id, 'customer_id' => $other->id,
            'sale_id' => $sale->id, 'entry_type' => 'cash', 'amount' => '1.00', 'outstanding_amount' => '1.00', 'due_date' => today()]);
    }

    public function test_reviewer_can_request_changes_and_rejection_is_historical(): void
    {
        [$owner, $seller, , $quote] = $this->acceptedQuote();
        $service = app(SaleApplicationService::class);
        $application = $service->fromQuotation($quote, $seller->user);
        $application->update(['requested_first_due_date' => CarbonImmutable::today()->addDay()->toDateString()]);
        $service->submit($application, $seller->user);
        $service->requestChanges($application, $owner->user, 'Modificar anticipo');
        $service->updateTerms($application, $seller->user, ['requested_down_payment' => '2.00']);
        $service->submit($application, $seller->user);
        $assessment = $service->assess($application, $owner->user, false, [], 'No aprobado');
        $this->assertSame('rejected', $assessment->status);
        $this->assertSame('rejected', $application->fresh()->status);
        $this->assertSame('2.00', $application->fresh()->requested_down_payment);
        $this->assertSame(['draft', 'pending_review', 'changes_requested', 'pending_review', 'rejected'], $application->statusHistory()->pluck('to_status')->all());
        $this->expectException(ValidationException::class);
        app(ConfirmSaleAction::class)->execute($application, $owner->user);
    }

    public function test_late_fee_policy_periods_cannot_overlap(): void
    {
        $owner = $this->member('owner');
        $service = app(LateFeePolicyService::class);
        $service->create($owner->tenant, $owner->user, ['effective_from' => '2028-01-01', 'effective_until' => '2028-01-31',
            'grace_days' => 2, 'daily_basis_points' => 50, 'cap_basis_points' => 500]);
        $this->expectException(ValidationException::class);
        $service->create($owner->tenant, $owner->user, ['effective_from' => '2028-01-31', 'effective_until' => '2028-02-28',
            'grace_days' => 2, 'daily_basis_points' => 50, 'cap_basis_points' => 500]);
    }

    public function test_dashboard_flow_creates_a_sale_from_accepted_quotation(): void
    {
        Queue::fake();
        [$owner, $seller, , $quote] = $this->acceptedQuote(false);
        $this->actingAs($seller->user)->withSession(['active_tenant_id' => $seller->tenant_id])
            ->post('/admin/sale-applications/from-quotation/'.$quote->id)->assertRedirect();
        $application = SaleApplication::forTenant($owner->tenant_id)->sole();
        $this->post('/admin/sale-applications/'.$application->id.'/submit')->assertRedirect();
        $this->actingAs($owner->user)->withSession(['active_tenant_id' => $owner->tenant_id])
            ->post('/admin/sale-applications/'.$application->id.'/assess', ['decision' => 'approved'])->assertRedirect();
        $this->post('/admin/sale-applications/'.$application->id.'/confirm')->assertRedirect();
        $this->assertDatabaseHas('sales', ['tenant_id' => $owner->tenant_id, 'sale_application_id' => $application->id, 'status' => 'confirmed']);
    }
}
