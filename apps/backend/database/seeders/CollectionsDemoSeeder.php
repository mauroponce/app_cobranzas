<?php

namespace Database\Seeders;

use App\Domain\Collections\CollectionAssignmentService;
use App\Domain\Collections\PaymentService;
use App\Domain\Commercial\Decimal;
use App\Domain\Commercial\QuotationService;
use App\Domain\Sales\ConfirmSaleAction;
use App\Domain\Sales\SaleApplicationService;
use App\Models\CollectionRoute;
use App\Models\CollectionTeam;
use App\Models\CollectionTeamMember;
use App\Models\CollectionVisit;
use App\Models\CollectionZone;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
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
        $this->routes($tenant, $adminMembership, $firstCollectorMembership, $secondCollectorMembership, $brunoSale, $anaSale, $zones);
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

    private function routes(Tenant $tenant, TenantMembership $admin, TenantMembership $firstCollector, TenantMembership $secondCollector, Sale $brunoSale, Sale $anaSale, array $zones): void
    {
        $date = today($tenant->timezone)->toDateString();
        $north = CollectionRoute::firstOrCreate([
            'tenant_id' => $tenant->id,
            'route_date' => $date,
            'collector_membership_id' => $firstCollector->id,
            'name' => 'DEMO: ruta norte',
        ], [
            'zone_id' => $zones['north']->id,
            'description' => 'DEMO: hoja publicada para iniciar desde Expo con collector@trotamundos.local y registrar una declaración.',
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
            'description' => 'DEMO: hoja publicada para el cobrador de zona sur.',
            'status' => CollectionRoute::PUBLISHED,
            'created_by_membership_id' => $admin->id,
            'published_by_membership_id' => $admin->id,
            'published_at' => now(),
            'version' => 1,
        ]);
        $this->visits($north, [$anaSale, $brunoSale]);
    }

    /** @param list<Sale> $sales */
    private function visits(CollectionRoute $route, array $sales): void
    {
        foreach ($sales as $index => $sale) {
            $entries = CustomerAccountEntry::forTenant($route->tenant_id)
                ->where('sale_id', $sale->id)
                ->where('status', 'open')
                ->where('outstanding_amount', '>', 0)
                ->orderBy('due_date')
                ->get();

            CollectionVisit::firstOrCreate([
                'tenant_id' => $route->tenant_id,
                'route_id' => $route->id,
                'sale_id' => $sale->id,
            ], [
                'customer_id' => $sale->customer_id,
                'sequence' => $index + 1,
                'status' => 'pending',
                'scheduled_amount' => Decimal::format(
                    $entries->sum(fn (CustomerAccountEntry $entry) => Decimal::toInteger($entry->outstanding_amount)),
                ),
                'obligation_snapshot' => $entries->map(fn (CustomerAccountEntry $entry): array => [
                    'account_entry_id' => $entry->id,
                    'installment_id' => $entry->installment_id,
                    'outstanding_amount' => $entry->outstanding_amount,
                    'due_date' => $entry->due_date?->toDateString(),
                ])->all(),
            ]);
        }
    }
}
