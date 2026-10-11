<?php

namespace Tests\Feature;

use App\Domain\Commercial\CustomerService;
use App\Domain\Commercial\Decimal;
use App\Domain\Commercial\PriceService;
use App\Domain\Commercial\QuotationService;
use App\Livewire\QuotationBuilder;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\ProductPrice;
use App\Models\Quotation;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role = 'owner', ?Tenant $tenant = null): TenantMembership
    {
        $tenant ??= Tenant::create(['name' => 'Empresa '.uniqid(), 'slug' => 'empresa-'.uniqid()]);
        $user = User::factory()->create();

        return TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active', 'joined_at' => now()]);
    }

    private function catalog(Tenant $tenant): array
    {
        $list = PriceList::create(['tenant_id' => $tenant->id, 'name' => 'Pública', 'code' => 'PUBLIC', 'type' => 'public', 'is_default' => true, 'is_active' => true]);
        $product = Product::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-1', 'name' => 'Producto', 'status' => 'active']);
        ProductPrice::create(['tenant_id' => $tenant->id, 'price_list_id' => $list->id, 'product_id' => $product->id, 'amount' => '10.01']);

        return [$list, $product];
    }

    private function customer(TenantMembership $member): Customer
    {
        return app(CustomerService::class)->create($member->tenant, $member->user, ['first_name' => 'Ana', 'last_name' => 'Demo', 'document_type' => 'DNI', 'document_number' => (string) random_int(10000000, 99999999)]);
    }

    private function quote(TenantMembership $seller, Customer $customer, PriceList $list, Product $product): Quotation
    {
        $quote = app(QuotationService::class)->create($seller->tenant, $seller->user, ['customer_id' => $customer->id, 'price_list_id' => $list->id]);
        app(QuotationService::class)->updateDraft($quote, $seller->user, ['sale_modality' => 'financed', 'down_payment_amount' => '1.00', 'installment_count' => 3, 'installment_frequency' => 'monthly', 'items' => [['product_id' => $product->id, 'quantity' => '1.500']]]);

        return $quote->fresh();
    }

    public function test_customers_can_be_created_updated_and_scoped(): void
    {
        $owner = $this->member();
        $foreign = $this->member();
        $this->actingAs($owner->user)->post('/admin/customers', ['first_name' => 'Ana', 'last_name' => 'Demo', 'document_type' => 'DNI', 'document_number' => '12.345.678'])->assertRedirect();
        $customer = Customer::firstOrFail();
        $this->assertSame('12345678', $customer->document_normalized);
        $this->patch('/admin/customers/'.$customer->id, ['first_name' => 'Ana María', 'last_name' => 'Demo', 'document_type' => 'DNI', 'document_number' => '12.345.678'])->assertRedirect();
        $this->assertSame('Ana María', $customer->fresh()->first_name);
        $this->actingAs($foreign->user)->withSession(['active_tenant_id' => $foreign->tenant_id])->get('/admin/customers/'.$customer->id)->assertNotFound();
        $this->patch('/admin/customers/'.$customer->id, ['first_name' => 'Intruso', 'last_name' => 'Demo'])->assertNotFound();
        $this->assertDatabaseHas('audit_events', ['action' => 'customer.updated', 'tenant_id' => $owner->tenant_id]);
    }

    public function test_normalized_documents_are_unique_inside_tenant(): void
    {
        $owner = $this->member();
        $this->actingAs($owner->user)->post('/admin/customers', ['first_name' => 'Ana', 'last_name' => 'Demo', 'document_type' => 'DNI', 'document_number' => '12.345.678'])->assertRedirect();
        $this->post('/admin/customers', ['first_name' => 'Otra', 'last_name' => 'Persona', 'document_type' => 'DNI', 'document_number' => '12345678'])->assertSessionHasErrors('document_number');
        $this->assertSame(1, Customer::count());
        $foreign = $this->member();
        $this->actingAs($foreign->user)->withSession(['active_tenant_id' => $foreign->tenant_id])->post('/admin/customers', ['first_name' => 'Otra', 'last_name' => 'Persona', 'document_type' => 'DNI', 'document_number' => '12345678'])->assertRedirect();
        $this->assertSame(2, Customer::count());
    }

    public function test_primary_address_is_unique_and_cross_tenant_foreign_key_is_enforced(): void
    {
        $owner = $this->member();
        $customer = $this->customer($owner);
        $service = app(CustomerService::class);
        $a = $service->saveAddress($customer, $owner->user, ['label' => 'Casa', 'street' => 'Falsa', 'street_number' => '1', 'city' => 'Demo', 'province' => 'Demo', 'is_primary' => true]);
        $b = $service->saveAddress($customer, $owner->user, ['label' => 'Trabajo', 'street' => 'Falsa', 'street_number' => '2', 'city' => 'Demo', 'province' => 'Demo', 'is_primary' => true]);
        $this->assertFalse($a->fresh()->is_primary);
        $this->assertTrue($b->fresh()->is_primary);
        $foreign = $this->member();
        $foreignCustomer = $this->customer($foreign);
        $this->expectException(QueryException::class);
        CustomerAddress::create(['tenant_id' => $owner->tenant_id, 'customer_id' => $foreignCustomer->id, 'label' => 'Cruce', 'street' => 'Falsa', 'street_number' => '3', 'city' => 'Demo', 'province' => 'Demo']);
    }

    public function test_seller_sees_public_price_but_not_internal_prices_or_costs(): void
    {
        $owner = $this->member();
        $seller = $this->member('seller', $owner->tenant);
        [$list, $product] = $this->catalog($owner->tenant);
        $internal = PriceList::create(['tenant_id' => $owner->tenant_id, 'name' => 'Interna secreta', 'code' => 'INT', 'type' => 'internal', 'is_active' => true]);
        ProductPrice::create(['tenant_id' => $owner->tenant_id, 'price_list_id' => $internal->id, 'product_id' => $product->id, 'amount' => '5.00']);
        ProductCost::create(['tenant_id' => $owner->tenant_id, 'product_id' => $product->id, 'amount' => '4.00']);
        $this->actingAs($seller->user)->get('/admin/products/'.$product->id)->assertOk()->assertSee('10,01')->assertDontSee('Interna secreta')->assertDontSee('4,00');
        $this->post('/admin/products/'.$product->id.'/costs', ['amount' => '7.00'])->assertForbidden();
        $this->post('/logout');
        $token = $seller->user->createToken('test')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $this->getJson('/api/v1/products/'.$product->id)->assertOk()->assertDontSee('Interna secreta')->assertDontSee('4.00');
    }

    public function test_internal_list_cannot_be_used_for_customer_quotation(): void
    {
        $owner = $this->member();
        [$public, $product] = $this->catalog($owner->tenant);
        $customer = $this->customer($owner);
        $internal = PriceList::create(['tenant_id' => $owner->tenant_id, 'name' => 'Interna', 'code' => 'INT', 'type' => 'internal', 'is_active' => true]);
        ProductPrice::create(['tenant_id' => $owner->tenant_id, 'price_list_id' => $internal->id, 'product_id' => $product->id, 'amount' => '1.00']);
        $this->actingAs($owner->user)->post('/admin/quotations', ['customer_id' => $customer->id, 'price_list_id' => $internal->id])->assertForbidden();
        $this->get('/admin/quotations/create')->assertOk()->assertDontSee('Interna');
    }

    public function test_price_periods_cannot_overlap_and_are_date_determined(): void
    {
        $owner = $this->member();
        [$list, $product] = $this->catalog($owner->tenant);
        $price = ProductPrice::firstOrFail();
        $price->update(['valid_until' => today()->subDay()->toDateString()]);
        ProductPrice::create(['tenant_id' => $owner->tenant_id, 'price_list_id' => $list->id, 'product_id' => $product->id, 'amount' => '15.00', 'valid_from' => today()]);
        $this->assertSame('15.00', app(PriceService::class)->applicable($product, $list, now())->amount);
        $this->expectException(QueryException::class);
        ProductPrice::create(['tenant_id' => $owner->tenant_id, 'price_list_id' => $list->id, 'product_id' => $product->id, 'amount' => '16.00', 'valid_from' => today()]);
    }

    public function test_quotation_calculation_rounding_and_snapshot_survive_price_change(): void
    {
        $owner = $this->member();
        $seller = $this->member('seller', $owner->tenant);
        [$list, $product] = $this->catalog($owner->tenant);
        $customer = $this->customer($seller);
        $quote = $this->quote($seller, $customer, $list, $product);
        $this->assertSame('15.02', $quote->total_amount);
        $this->assertSame('14.02', $quote->financed_amount);
        $this->assertNull($quote->estimated_installment_amount);
        $this->assertSame('15.02', $quote->items()->first()->line_total);
        $this->assertSame('10.01', $quote->items()->first()->unit_price);
        $customer->update(['first_name' => 'Cambió']);
        $this->assertSame('Ana', $quote->fresh()->customer_snapshot['first_name']);
        $this->assertSame('1.01', Decimal::format(Decimal::line('1.005', '1.00')));
    }

    public function test_quotation_states_permissions_and_immutable_approved_quote(): void
    {
        $owner = $this->member();
        $seller = $this->member('seller', $owner->tenant);
        [$list, $product] = $this->catalog($owner->tenant);
        $quote = $this->quote($seller, $this->customer($seller), $list, $product);
        $service = app(QuotationService::class);
        $service->transition($quote, $seller->user, 'pending_review');
        $this->actingAs($seller->user)->get('/admin/quotations/'.$quote->id)->assertOk()->assertSee('En revisión');
        $this->post('/admin/quotations/'.$quote->id.'/transition', ['status' => 'approved'])->assertForbidden();
        $this->actingAs($owner->user)->post('/admin/quotations/'.$quote->id.'/transition', ['status' => 'approved'])->assertRedirect();
        $this->actingAs($seller->user)->patch('/admin/quotations/'.$quote->id, ['sale_modality' => 'cash', 'price_list_id' => $list->id])->assertSessionHasErrors('status');
        $this->assertDatabaseHas('quotation_status_history', ['quotation_id' => $quote->id, 'from_status' => 'pending_review', 'to_status' => 'approved']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $quote->id, 'action' => 'quotation.approved']);
    }

    public function test_quotation_visibility_and_api_are_scoped_to_seller_and_tenant(): void
    {
        $owner = $this->member();
        $seller = $this->member('seller', $owner->tenant);
        $otherSeller = $this->member('seller', $owner->tenant);
        [$list, $product] = $this->catalog($owner->tenant);
        $quote = $this->quote($seller, $this->customer($seller), $list, $product);
        $this->actingAs($otherSeller->user)->get('/admin/quotations/'.$quote->id)->assertForbidden();
        $foreign = $this->member();
        $this->actingAs($foreign->user)->withSession(['active_tenant_id' => $foreign->tenant_id])->get('/admin/quotations/'.$quote->id)->assertNotFound();
        $this->post('/logout');
        $token = $otherSeller->user->createToken('test')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/tenant/select', ['tenant_id' => $owner->tenant_id])->assertOk();
        $this->getJson('/api/v1/quotations/'.$quote->id)->assertForbidden();
        $this->getJson('/api/v1/quotations')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_pdf_is_available_only_after_approval_and_excludes_internal_notes(): void
    {
        $owner = $this->member();
        $seller = $this->member('seller', $owner->tenant);
        [$list, $product] = $this->catalog($owner->tenant);
        $quote = $this->quote($seller, $this->customer($seller), $list, $product);
        $quote->update(['internal_notes' => 'SECRETO INTERNO']);
        $this->actingAs($seller->user)->get('/admin/quotations/'.$quote->id.'/pdf')->assertStatus(422);
        $service = app(QuotationService::class);
        $service->transition($quote, $seller->user, 'pending_review');
        $service->transition($quote, $owner->user, 'approved');
        $response = $this->get('/admin/quotations/'.$quote->id.'/pdf')->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringNotContainsString('SECRETO INTERNO', $response->getContent());
    }

    public function test_livewire_builder_saves_multiple_items_using_authorized_prices(): void
    {
        $seller = $this->member('seller');
        [$list, $product] = $this->catalog($seller->tenant);
        $customer = $this->customer($seller);
        $quote = app(QuotationService::class)->create($seller->tenant, $seller->user, ['customer_id' => $customer->id, 'price_list_id' => $list->id]);
        $this->actingAs($seller->user)->withSession(['active_tenant_id' => $seller->tenant_id]);
        Livewire::test(QuotationBuilder::class, ['quotation' => $quote])
            ->set('selectedProductId', $product->id)->set('newQuantity', '2.500')->call('addItem')
            ->set('saleModality', 'financed')->set('downPaymentAmount', '5.00')
            ->set('installmentCount', '4')->set('installmentFrequency', 'monthly')
            ->call('save')->assertRedirect(route('quotations.show', $quote));
        $this->assertSame('25.03', $quote->fresh()->total_amount);
        $this->assertSame('20.03', $quote->fresh()->financed_amount);
        $this->assertSame(1, $quote->items()->count());
    }

    public function test_foreign_customer_product_and_list_cannot_be_used_in_quotation(): void
    {
        $owner = $this->member();
        $seller = $this->member('seller', $owner->tenant);
        [$list, $product] = $this->catalog($owner->tenant);
        $foreign = $this->member();
        [$foreignList, $foreignProduct] = $this->catalog($foreign->tenant);
        $foreignCustomer = $this->customer($foreign);
        $customer = $this->customer($seller);
        $this->actingAs($seller->user)->post('/admin/quotations', ['customer_id' => $foreignCustomer->id, 'price_list_id' => $list->id])->assertNotFound();
        $this->post('/admin/quotations', ['customer_id' => $customer->id, 'price_list_id' => $foreignList->id])->assertNotFound();
        $quote = $this->quote($seller, $customer, $list, $product);
        $this->patch('/admin/quotations/'.$quote->id, ['price_list_id' => $list->id, 'sale_modality' => 'cash', 'items' => [['product_id' => $foreignProduct->id, 'quantity' => '1']]])->assertNotFound();
        $this->assertSame('15.02', $quote->fresh()->total_amount);
    }

    public function test_activity_and_duplication_preserve_original_quote(): void
    {
        $owner = $this->member();
        $seller = $this->member('seller', $owner->tenant);
        [$list, $product] = $this->catalog($owner->tenant);
        $quote = $this->quote($seller, $this->customer($seller), $list, $product);
        $this->actingAs($seller->user)->post('/admin/quotations/'.$quote->id.'/activities', ['type' => 'call', 'notes' => 'Llamada de seguimiento'])->assertRedirect();
        $this->post('/admin/quotations/'.$quote->id.'/duplicate')->assertRedirect();
        $copy = Quotation::where('id', '!=', $quote->id)->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertSame($quote->total_amount, $copy->total_amount);
        $this->assertSame(1, $quote->activities()->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'quotation.activity_added', 'tenant_id' => $owner->tenant_id]);
    }

    public function test_quotation_detail_offers_a_manual_whatsapp_link_when_the_customer_has_a_number(): void
    {
        $seller = $this->member('seller');
        [$list, $product] = $this->catalog($seller->tenant);
        $customer = $this->customer($seller);
        $customer->update(['whatsapp_phone' => '+54 9 11 5555-1234']);
        $quote = $this->quote($seller, $customer, $list, $product);

        $this->actingAs($seller->user)
            ->get('/admin/quotations/'.$quote->id)
            ->assertOk()
            ->assertSee('Abrir WhatsApp')
            ->assertSee('https://wa.me/5491155551234', false);
    }

    public function test_invalid_state_transition_and_self_approval_are_rejected(): void
    {
        $owner = $this->member();
        [$list, $product] = $this->catalog($owner->tenant);
        $quote = $this->quote($owner, $this->customer($owner), $list, $product);
        $service = app(QuotationService::class);
        try {
            $service->transition($quote, $owner->user, 'accepted');
            $this->fail('An invalid transition was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
        $service->transition($quote, $owner->user, 'pending_review');
        $this->actingAs($owner->user)->post('/admin/quotations/'.$quote->id.'/transition', ['status' => 'approved'])->assertSessionHasErrors('status');
        $this->assertSame('pending_review', $quote->fresh()->status);
    }

    public function test_expiry_requires_validity_date_in_the_past(): void
    {
        $owner = $this->member();
        $seller = $this->member('seller', $owner->tenant);
        [$list, $product] = $this->catalog($owner->tenant);
        $quote = $this->quote($seller, $this->customer($seller), $list, $product);
        $service = app(QuotationService::class);
        $service->transition($quote, $seller->user, 'pending_review');
        $service->transition($quote, $owner->user, 'approved');
        $service->transition($quote, $seller->user, 'sent');
        $this->actingAs($owner->user)->post('/admin/quotations/'.$quote->id.'/transition', ['status' => 'expired'])->assertSessionHasErrors('valid_until');
        $quote->update(['valid_until' => today()->subDay()]);
        $this->post('/admin/quotations/'.$quote->id.'/transition', ['status' => 'expired'])->assertRedirect();
        $this->assertSame('expired', $quote->fresh()->status);
    }

    public function test_price_rotation_keeps_historical_quotation_amount(): void
    {
        $owner = $this->member();
        [$list, $product] = $this->catalog($owner->tenant);
        $quote = $this->quote($owner, $this->customer($owner), $list, $product);
        $oldPrice = ProductPrice::firstOrFail();
        $this->actingAs($owner->user)->patch('/admin/products/'.$product->id.'/prices/'.$oldPrice->id, ['valid_until' => today()->subDay()->toDateString()])->assertRedirect();
        $this->post('/admin/products/'.$product->id.'/prices', ['price_list_id' => $list->id, 'amount' => '20.00', 'valid_from' => today()->toDateString()])->assertRedirect();
        $this->assertSame('10.01', $quote->items()->first()->unit_price);
        $this->assertSame('20.00', app(PriceService::class)->applicable($product, $list, now())->amount);
    }

    public function test_livewire_builder_rechecks_membership_when_it_changes(): void
    {
        $seller = $this->member('seller');
        [$list, $product] = $this->catalog($seller->tenant);
        $quote = $this->quote($seller, $this->customer($seller), $list, $product);
        $this->actingAs($seller->user)->withSession(['active_tenant_id' => $seller->tenant_id]);
        Livewire::test(QuotationBuilder::class, ['quotation' => $quote])->assertSee('Productos');
        $seller->update(['status' => 'suspended']);
        Livewire::test(QuotationBuilder::class, ['quotation' => $quote])->assertForbidden();
    }
}
