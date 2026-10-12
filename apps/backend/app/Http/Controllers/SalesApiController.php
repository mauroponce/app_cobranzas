<?php

namespace App\Http\Controllers;

use App\Domain\Sales\AccountStatementService;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\LateFeePolicy;
use App\Models\Sale;
use App\Models\SaleApplication;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SalesApiController extends Controller
{
    public function applications(Request $request)
    {
        Gate::authorize('sales.view');
        $tenant = $request->attributes->get('activeTenant');
        $rows = SaleApplication::forTenant($tenant)->when(! Gate::allows('sales.view_all'), fn ($q) => $q->where('seller_id', $request->user()->id))
            ->latest()->paginate(20, ['id', 'application_number', 'customer_id', 'seller_id', 'sale_type', 'status', 'requested_total', 'created_at']);

        return response()->json($rows);
    }

    public function application(Request $request, SaleApplication $application)
    {
        $this->authorizeResource($request, $application);
        $application->load('items');

        return response()->json(['data' => $application->only(['id', 'application_number', 'quotation_id', 'customer_id', 'sale_type', 'status', 'requested_total', 'requested_down_payment', 'requested_installment_count', 'requested_frequency', 'requested_first_due_date']) + ['items' => $application->items->map->only(['description', 'quantity', 'unit_price', 'line_total'])]]);
    }

    public function sales(Request $request)
    {
        Gate::authorize('sales.view');
        $rows = Sale::forTenant($request->attributes->get('activeTenant'))->when(! Gate::allows('sales.view_all'), fn ($q) => $q->where('seller_id', $request->user()->id))
            ->latest()->paginate(20, ['id', 'sale_number', 'customer_id', 'seller_id', 'sale_type', 'status', 'total_amount', 'confirmed_at']);

        return response()->json($rows);
    }

    public function sale(Request $request, Sale $sale)
    {
        $this->authorizeResource($request, $sale);
        $sale->load('plan.installments');

        return response()->json(['data' => $sale->only(['id', 'sale_number', 'customer_id', 'sale_type', 'status', 'total_amount', 'down_payment_amount', 'financed_principal', 'financing_charge_amount', 'financed_total', 'confirmed_at']) + ['installments' => $sale->plan?->installments->map->only(['sequence', 'due_date', 'amount', 'outstanding_amount', 'status']) ?? []]]);
    }

    public function account(Request $request, Customer $customer, AccountStatementService $statement)
    {
        Gate::authorize('sales.view');
        abort_unless($customer->tenant_id === $request->attributes->get('activeTenant')->id, 404);
        $sellerId = Gate::allows('sales.view_all') ? null : $request->user()->id;
        $asOf = CarbonImmutable::today($request->attributes->get('activeTenant')->timezone);
        $entries = CustomerAccountEntry::forTenant($customer->tenant_id)->where('customer_id', $customer->id)
            ->when($sellerId, fn ($q) => $q->whereHas('sale', fn ($sale) => $sale->where('seller_id', $sellerId)))
            ->orderBy('due_date')->get(['id', 'sale_id', 'installment_id', 'entry_type', 'amount', 'outstanding_amount', 'due_date', 'status']);
        $entries->each(fn ($entry) => $entry->setAttribute('calculated_late_fee', $statement->lateFee($entry, $asOf)));

        return response()->json(['data' => ['balances' => $statement->balances($customer, $asOf, $sellerId), 'entries' => $entries]]);
    }

    public function policies(Request $request)
    {
        Gate::authorize('sales.view');

        return response()->json(['data' => LateFeePolicy::forTenant($request->attributes->get('activeTenant'))->orderByDesc('version')->get(['version', 'effective_from', 'effective_until', 'grace_days', 'daily_basis_points', 'cap_basis_points'])]);
    }

    private function authorizeResource(Request $request, object $resource): void
    {
        Gate::authorize('sales.view');
        abort_unless($resource->tenant_id === $request->attributes->get('activeTenant')->id, 404);
        abort_unless(Gate::allows('sales.view_all') || $resource->seller_id === $request->user()->id, 403);
    }
}
