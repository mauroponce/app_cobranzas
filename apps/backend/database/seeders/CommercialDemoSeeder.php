<?php

namespace Database\Seeders;

use App\Domain\Commercial\CustomerService;
use App\Domain\Commercial\QuotationService;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductCost;
use App\Models\ProductPrice;
use App\Models\Quotation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommercialDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }
        $tenant = Tenant::where('slug', 'trotamundos')->first();
        $seller = User::where('email', 'seller@trotamundos.local')->first();
        $admin = User::where('email', config('demo.admin_email') ?: 'admin@trotamundos.local')->first();
        if (! $tenant || ! $seller || ! $admin) {
            return;
        }
        $customerService = app(CustomerService::class);
        $customers = [];
        foreach ([['Ana', 'Demo', 'DEMO-001'], ['Bruno', 'Ejemplo', 'DEMO-002'], ['Carla', 'Muestra', 'DEMO-003']] as [$first, $last, $document]) {
            $customer = Customer::forTenant($tenant)->where('document_normalized', str_replace('-', '', $document))->first();
            $customer ??= $customerService->create($tenant, $seller, ['first_name' => $first, 'last_name' => $last, 'document_type' => 'OTHER', 'document_number' => $document, 'notes' => 'DEMO: cliente ficticio']);
            if ($customer->addresses()->count() === 0) {
                $customerService->saveAddress($customer, $seller, ['label' => 'Casa', 'street' => 'Calle Ficticia', 'street_number' => '100', 'city' => 'Ciudad Ejemplo', 'province' => 'Provincia Ejemplo', 'is_primary' => true]);
            }
            $customers[] = $customer;
        }
        $categories = [];
        foreach (['Electrodomésticos', 'Muebles'] as $name) {
            $categories[$name] = ProductCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name]);
        }
        $products = [];
        foreach ([['DEMO-HEL-01', 'Heladera demo', 'Electrodomésticos', '500000.00', '400000.00'], ['DEMO-LAV-01', 'Lavarropas demo', 'Electrodomésticos', '350000.00', '270000.00'], ['DEMO-MES-01', 'Mesa demo', 'Muebles', '180000.00', '120000.00']] as [$sku, $name, $category, $price, $cost]) {
            $product = Product::firstOrCreate(['tenant_id' => $tenant->id, 'sku' => $sku], ['name' => $name, 'category_id' => $categories[$category]->id, 'description' => 'DEMO: producto ficticio', 'status' => 'active']);
            $products[] = $product;
        }
        $public = PriceList::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'DEMO-PUBLIC'], ['name' => 'Público demo', 'type' => 'public', 'currency' => 'ARS', 'is_default' => true, 'is_active' => true]);
        $internal = PriceList::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'DEMO-INTERNAL'], ['name' => 'Interno demo', 'type' => 'internal', 'currency' => 'ARS', 'is_default' => false, 'is_active' => true]);
        foreach ($products as $index => $product) {
            $amount = ['500000.00', '350000.00', '180000.00'][$index];
            $cost = ['400000.00', '270000.00', '120000.00'][$index];
            ProductPrice::firstOrCreate(['tenant_id' => $tenant->id, 'price_list_id' => $public->id, 'product_id' => $product->id], ['amount' => $amount]);
            ProductPrice::firstOrCreate(['tenant_id' => $tenant->id, 'price_list_id' => $internal->id, 'product_id' => $product->id], ['amount' => $cost]);
            ProductCost::firstOrCreate(['tenant_id' => $tenant->id, 'product_id' => $product->id], ['amount' => $cost]);
        }
        $service = app(QuotationService::class);
        foreach (['draft', 'pending_review', 'approved', 'sent', 'accepted'] as $index => $status) {
            $marker = 'DEMO: presupuesto '.(['draft' => 'borrador', 'pending_review' => 'en revisión', 'approved' => 'aprobado', 'sent' => 'enviado', 'accepted' => 'aceptado'][$status]);
            $existing = Quotation::forTenant($tenant)->whereIn('notes', [$marker, 'DEMO: presupuesto '.$status])->first();
            if ($existing) {
                if ($existing->notes !== $marker) {
                    $existing->update(['notes' => $marker]);
                }

                continue;
            }
            $quote = $service->create($tenant, $seller, ['customer_id' => $customers[$index % count($customers)]->id, 'price_list_id' => $public->id]);
            $service->updateDraft($quote, $seller, ['notes' => $marker, 'sale_modality' => 'financed', 'down_payment_amount' => '50000.00', 'installment_count' => 6, 'installment_frequency' => 'monthly', 'items' => [['product_id' => $products[$index % count($products)]->id, 'quantity' => '1']]]);
            if ($status !== 'draft') {
                $service->transition($quote, $seller, 'pending_review');
            }
            if (in_array($status, ['approved', 'sent', 'accepted'])) {
                $service->transition($quote, $admin, 'approved');
            }
            if (in_array($status, ['sent', 'accepted'])) {
                $service->transition($quote, $seller, 'sent');
            }
            if ($status === 'accepted') {
                $service->transition($quote, $seller, 'accepted');
            }
            $service->activity($quote, $seller, ['type' => 'follow_up', 'notes' => 'DEMO: llamada de seguimiento ficticia']);
        }
    }
}
