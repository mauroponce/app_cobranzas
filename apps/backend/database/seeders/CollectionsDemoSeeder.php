<?php

namespace Database\Seeders;

use App\Domain\Collections\CollectionAssignmentService;
use App\Domain\Collections\PaymentService;
use App\Domain\Commercial\QuotationService;
use App\Domain\Sales\ConfirmSaleAction;
use App\Domain\Sales\SaleApplicationService;
use App\Models\CollectionRoute;
use App\Models\CollectionRouteStop;
use App\Models\CollectionTeam;
use App\Models\CollectionTeamMember;
use App\Models\CollectionZone;
use App\Models\Customer;
use App\Models\CustomerCollectionZoneAssignment;
use App\Models\Payment;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\SaleApplication;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Seeder;

class CollectionsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $tenant = Tenant::where('slug', 'trotamundos')->first();
        $admin = User::where('email', config('demo.admin_email') ?: 'admin@trotamundos.local')->first();
        $seller = User::where('email', 'seller@trotamundos.local')->first();
        $firstCollector = User::where('email', 'collector@trotamundos.local')->first();
        $secondCollector = User::where('email', 'collector.sur@trotamundos.local')->first();
        if (! $tenant || ! $admin || ! $seller || ! $firstCollector || ! $secondCollector) {
            return;
        }

        $customers = Customer::forTenant($tenant)->whereIn('document_normalized', ['DEMO001', 'DEMO002', 'DEMO003'])->get()->keyBy('document_normalized');
        $products = Product::forTenant($tenant)->whereIn('sku', ['DEMO-HEL-01', 'DEMO-LAV-01', 'DEMO-MES-01'])->get()->keyBy('sku');
        $priceList = PriceList::forTenant($tenant)->where('code', 'DEMO-PUBLIC')->first();
        $adminMembership = $this->membership($tenant, $admin);
        $firstCollectorMembership = $this->membership($tenant, $firstCollector);
        $secondCollectorMembership = $this->membership($tenant, $secondCollector);
        if ($customers->count() !== 3 || $products->count() !== 3 || ! $priceList || ! $adminMembership || ! $firstCollectorMembership || ! $secondCollectorMembership) {
            return;
        }

        $zones = $this->zones($tenant);
        $this->assignZones($tenant, $adminMembership, $customers, $zones);
        $this->team($tenant, $adminMembership, $firstCollectorMembership, $secondCollectorMembership);
        $this->assignCollectors($tenant, $admin, $customers, $firstCollectorMembership, $secondCollectorMembership);

        $quotationService = app(QuotationService::class);
        $brunoQuote = $this->acceptedQuotation($tenant, $seller, $admin, $quotationService, $priceList, $customers['DEMO002'], $products['DEMO-LAV-01'], 'DEMO: presupuesto aceptado para cobranza de Bruno');
        $anaQuote = $this->acceptedQuotation($tenant, $seller, $admin, $quotationService, $priceList, $customers['DEMO001'], $products['DEMO-HEL-01'], 'DEMO: presupuesto aceptado para cobranza de Ana');

        $brunoSale = $this->confirmedCashSale($brunoQuote, $seller, $admin);
        $anaSale = $this->confirmedCashSale($anaQuote, $seller, $admin);
        $this->payment($tenant, $firstCollector, $customers['DEMO002'], $brunoSale, '150000.00', 'DEMO: cobro parcial de Bruno');
        $this->payment($tenant, $firstCollector, $customers['DEMO001'], $anaSale, '200000.00', 'DEMO: cobro parcial de Ana');
        $this->routes($tenant, $adminMembership, $firstCollectorMembership, $secondCollectorMembership, $customers, $zones);
    }

    private function membership(Tenant $tenant, User $user): ?TenantMembership
    {
        return TenantMembership::forTenant($tenant)->where('user_id', $user->id)->where('status', 'active')->first();
    }

    private function zones(Tenant $tenant): array
    {
        return [
            'north' => CollectionZone::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'DEMO-NORTE'], ['name' => 'Zona norte demo', 'description' => 'DEMO: cartera ficticia de zona norte', 'status' => 'active']),
            'south' => CollectionZone::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'DEMO-SUR'], ['name' => 'Zona sur demo', 'description' => 'DEMO: cartera ficticia de zona sur', 'status' => 'active']),
        ];
    }

    private function assignZones(Tenant $tenant, TenantMembership $admin, $customers, array $zones): void
    {
        foreach (['DEMO001' => 'north', 'DEMO002' => 'north', 'DEMO003' => 'south'] as $document => $zone) {
            CustomerCollectionZoneAssignment::firstOrCreate([
                'tenant_id' => $tenant->id,
                'customer_id' => $customers[$document]->id,
                'zone_id' => $zones[$zone]->id,
                'unassigned_at' => null,
            ], [
                'assigned_by_membership_id' => $admin->id,
                'assigned_at' => now(),
                'reason' => 'DEMO: asignación inicial de zona',
            ]);
        }
    }

    private function team(Tenant $tenant, TenantMembership $supervisor, TenantMembership ...$collectors): void
    {
        $team = CollectionTeam::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Equipo demo de cobranzas'], ['description' => 'DEMO: equipo ficticio para revisar carteras', 'status' => 'active']);
        CollectionTeamMember::firstOrCreate(['tenant_id' => $tenant->id, 'team_id' => $team->id, 'membership_id' => $supervisor->id, 'left_at' => null], ['team_role' => 'supervisor', 'joined_at' => now()]);
        foreach ($collectors as $collector) {
            CollectionTeamMember::firstOrCreate(['tenant_id' => $tenant->id, 'team_id' => $team->id, 'membership_id' => $collector->id, 'left_at' => null], ['team_role' => 'collector', 'joined_at' => now()]);
        }
    }

    private function assignCollectors(Tenant $tenant, User $admin, $customers, TenantMembership $firstCollector, TenantMembership $secondCollector): void
    {
        $service = app(CollectionAssignmentService::class);
        foreach (['DEMO001' => $firstCollector, 'DEMO002' => $firstCollector, 'DEMO003' => $secondCollector] as $document => $collector) {
            $assigned = $customers[$document]->collectorAssignments()->whereNull('unassigned_at')->first();
            if (! $assigned || $assigned->collector_membership_id !== $collector->id) {
                $service->assign($tenant, $admin, $customers[$document], $collector, 'DEMO: cartera inicial');
            }
        }
    }

    private function acceptedQuotation(Tenant $tenant, User $seller, User $admin, QuotationService $service, PriceList $priceList, Customer $customer, Product $product, string $marker): Quotation
    {
        $quote = Quotation::forTenant($tenant)->where('notes', $marker)->first();
        if ($quote) {
            return $quote;
        }

        $quote = $service->create($tenant, $seller, ['customer_id' => $customer->id, 'price_list_id' => $priceList->id]);
        $service->updateDraft($quote, $seller, [
            'notes' => $marker,
            'sale_modality' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
            ]],
        ]);
        $service->transition($quote, $seller, 'pending_review');
        $service->transition($quote, $admin, 'approved');
        $service->transition($quote, $seller, 'sent');
        $service->transition($quote, $seller, 'accepted');

        return $quote;
    }

    private function confirmedCashSale(Quotation $quotation, User $seller, User $admin): Sale
    {
        $sale = Sale::forTenant($quotation->tenant_id)->where('quotation_id', $quotation->id)->first();
        if ($sale) {
            return $sale;
        }

        $service = app(SaleApplicationService::class);
        $application = SaleApplication::forTenant($quotation->tenant_id)->where('quotation_id', $quotation->id)->first();
        $application ??= $service->fromQuotation($quotation, $seller);
        if ($application->status === 'draft') {
            $application = $service->submit($application, $seller);
        }
        if ($application->status === 'pending_review') {
            $service->assess($application, $admin, true, [], 'DEMO: evaluación aprobada');
        }

        return app(ConfirmSaleAction::class)->execute($application->fresh(), $admin);
    }

    private function payment(Tenant $tenant, User $collector, Customer $customer, Sale $sale, string $amount, string $marker): void
    {
        if (Payment::forTenant($tenant)->where('notes', $marker)->exists()) {
            return;
        }

        app(PaymentService::class)->registerCash($tenant, $collector, [
            'customer_id' => $customer->id,
            'amount' => $amount,
            'received_at' => now()->subDays(2),
            'notes' => $marker,
            'allocations' => $sale->accountEntries()->where('status', 'open')->orderBy('due_date')->get()->map(fn ($entry) => ['account_entry_id' => $entry->id])->all(),
        ]);
    }

    private function routes(Tenant $tenant, TenantMembership $admin, TenantMembership $firstCollector, TenantMembership $secondCollector, $customers, array $zones): void
    {
        $date = now()->toDateString();
        $north = CollectionRoute::firstOrCreate([
            'tenant_id' => $tenant->id,
            'route_date' => $date,
            'collector_membership_id' => $firstCollector->id,
            'name' => 'DEMO: ruta norte',
        ], [
            'zone_id' => $zones['north']->id,
            'description' => 'DEMO: visitas de cobranza de Ana y Bruno',
            'status' => CollectionRoute::PUBLISHED,
            'created_by_membership_id' => $admin->id,
            'published_by_membership_id' => $admin->id,
            'published_at' => now(),
            'version' => 1,
        ]);
        $south = CollectionRoute::firstOrCreate([
            'tenant_id' => $tenant->id,
            'route_date' => $date,
            'collector_membership_id' => $secondCollector->id,
            'name' => 'DEMO: ruta sur',
        ], [
            'zone_id' => $zones['south']->id,
            'description' => 'DEMO: visita de cobranza de Carla',
            'status' => CollectionRoute::PUBLISHED,
            'created_by_membership_id' => $admin->id,
            'published_by_membership_id' => $admin->id,
            'published_at' => now(),
            'version' => 1,
        ]);
        $this->stops($north, $firstCollector, [$customers['DEMO001'], $customers['DEMO002']]);
        $this->stops($south, $secondCollector, [$customers['DEMO003']]);
    }

    private function stops(CollectionRoute $route, TenantMembership $collector, array $customers): void
    {
        foreach ($customers as $index => $customer) {
            $address = $customer->addresses()->where('is_primary', true)->first();
            CollectionRouteStop::updateOrCreate([
                'tenant_id' => $route->tenant_id,
                'route_id' => $route->id,
                'customer_id' => $customer->id,
            ], [
                'customer_address_id' => $address?->id,
                'sequence' => $index + 1,
                'assigned_collector_membership_id' => $collector->id,
                'status' => 'pending',
                'planned_notes' => 'DEMO: visita programada',
                'address_snapshot' => $address ? $address->only(['label', 'street', 'street_number', 'city', 'province']) : null,
            ]);
        }
    }
}
