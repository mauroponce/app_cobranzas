<?php

namespace App\Http\Controllers;

use App\Domain\Commercial\Decimal;
use App\Domain\Sales\AccountStatementService;
use App\Domain\Sales\CancelSaleAction;
use App\Domain\Sales\ConfirmSaleAction;
use App\Domain\Sales\DocumentStorageService;
use App\Domain\Sales\LateFeePolicyService;
use App\Domain\Sales\SaleApplicationService;
use App\Domain\Tenancy\Audit;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerDocument;
use App\Models\LateFeePolicy;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\SaleApplication;
use App\Models\SaleDocument;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    private function scoped(Request $request, object $model): void
    {
        abort_unless($model->tenant_id === $request->attributes->get('activeTenant')->id, 404);
    }

    public function applications(Request $request)
    {
        Gate::authorize('sales.view');
        $applications = SaleApplication::forTenant($request->attributes->get('activeTenant'))->with(['customer', 'quotation'])
            ->when(! Gate::allows('sales.view_all'), fn ($q) => $q->where('seller_id', $request->user()->id))->latest()->paginate(20);
        $quotations = Gate::allows('sales.create') ? Quotation::forTenant($request->attributes->get('activeTenant'))->where('status', 'accepted')->whereDoesntHave('saleApplication')
            ->when(! Gate::allows('sales.view_all'), fn ($q) => $q->where('seller_id', $request->user()->id))->orderBy('quotation_number')->get() : collect();

        return view('commercial.sales.applications', compact('applications', 'quotations'));
    }

    public function fromQuotation(Request $request, Quotation $quotation, SaleApplicationService $service)
    {
        $this->scoped($request, $quotation);
        Gate::authorize('sales.create');
        abort_unless(Gate::allows('sales.view_all') || $quotation->seller_id === $request->user()->id, 403);
        $application = $service->fromQuotation($quotation, $request->user());

        return redirect()->route('sale-applications.show', $application)->with('status', 'Solicitud creada.');
    }

    public function createDirect(Request $request)
    {
        Gate::authorize('sales.create_direct');
        $customers = Customer::forTenant($request->attributes->get('activeTenant'))->where('status', 'active')->orderBy('last_name')->get();
        $products = Product::forTenant($request->attributes->get('activeTenant'))->where('status', 'active')->orderBy('name')->get();

        return view('commercial.sales.direct', compact('customers', 'products'));
    }

    public function storeDirect(Request $request, SaleApplicationService $service)
    {
        Gate::authorize('sales.create_direct');
        $request->validate(['items' => ['required', 'array', 'max:50']]);
        $request->merge(['items' => array_values(array_filter($request->input('items', []), fn ($item) => is_array($item) && ! empty($item['product_id'])))]);
        $businessDate = CarbonImmutable::today($request->attributes->get('activeTenant')->timezone)->toDateString();
        $data = $request->validate(['customer_id' => ['required', 'uuid'], 'sale_type' => ['required', Rule::in(['cash', 'financed'])],
            'requested_down_payment' => ['nullable', 'decimal:0,2', 'min:0'], 'requested_installment_count' => ['nullable', 'integer', 'between:1,120'],
            'requested_frequency' => ['nullable', Rule::in(['daily', 'weekly', 'biweekly', 'monthly'])],
            'requested_first_due_date' => ['nullable', 'date', 'after_or_equal:'.$businessDate],
            'items' => ['required', 'array', 'min:1', 'max:50'], 'items.*.product_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'decimal:0,3', 'gt:0'], 'items.*.unit_price' => ['required', 'decimal:0,2', 'gt:0']]);
        $tenant = $request->attributes->get('activeTenant');
        $data['items'] = collect($data['items'])->map(function ($item) use ($tenant) {
            $product = Product::forTenant($tenant)->whereKey($item['product_id'])->where('status', 'active')->firstOrFail();

            return ['product_id' => $product->id, 'description' => $product->name, 'sku_snapshot' => $product->sku,
                'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'],
                'line_total' => Decimal::format(Decimal::line((string) $item['quantity'], (string) $item['unit_price']))];
        })->all();
        $application = $service->direct($tenant, $request->user(), $data);

        return redirect()->route('sale-applications.show', $application)->with('status', 'Solicitud directa creada.');
    }

    public function application(Request $request, SaleApplication $application)
    {
        $this->scoped($request, $application);
        Gate::authorize('view', $application);
        $application->load(['customer', 'items', 'assessments', 'documents.storedDocument', 'statusHistory', 'sale']);

        $products = $application->quotation_id ? collect() : Product::forTenant($application->tenant_id)->where('status', 'active')->orderBy('name')->get();

        return view('commercial.sales.application', compact('application', 'products'));
    }

    public function replaceDirectItems(Request $request, SaleApplication $application, SaleApplicationService $service)
    {
        $this->scoped($request, $application);
        $request->validate(['items' => ['required', 'array', 'max:50']]);
        $request->merge(['items' => array_values(array_filter($request->input('items', []), fn ($item) => is_array($item) && ! empty($item['product_id'])))]);
        $data = $request->validate(['items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'uuid'], 'items.*.quantity' => ['required', 'decimal:0,3', 'gt:0'],
            'items.*.unit_price' => ['required', 'decimal:0,2', 'gt:0']]);
        $data['items'] = collect($data['items'])->map(function ($item) use ($application) {
            $product = Product::forTenant($application->tenant_id)->whereKey($item['product_id'])->where('status', 'active')->firstOrFail();

            return ['product_id' => $product->id, 'description' => $product->name, 'sku_snapshot' => $product->sku,
                'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'],
                'line_total' => Decimal::format(Decimal::line((string) $item['quantity'], (string) $item['unit_price']))];
        })->all();
        $service->replaceDirectItems($application, $request->user(), $data['items']);

        return back()->with('status', 'Ítems actualizados.');
    }

    public function updateTerms(Request $request, SaleApplication $application, SaleApplicationService $service)
    {
        $this->scoped($request, $application);
        $businessDate = CarbonImmutable::today($request->attributes->get('activeTenant')->timezone)->toDateString();
        $data = $request->validate(['requested_down_payment' => ['nullable', 'decimal:0,2', 'min:0'],
            'requested_installment_count' => ['nullable', 'integer', 'between:1,120'],
            'requested_frequency' => ['nullable', Rule::in(['daily', 'weekly', 'biweekly', 'monthly'])],
            'requested_first_due_date' => ['nullable', 'date', 'after_or_equal:'.$businessDate]]);
        $service->updateTerms($application, $request->user(), $data);

        return back()->with('status', 'Condiciones actualizadas.');
    }

    public function submit(Request $request, SaleApplication $application, SaleApplicationService $service)
    {
        $this->scoped($request, $application);
        abort_unless($application->seller_id === $request->user()->id || Gate::allows('sales.view_all'), 403);
        $service->submit($application, $request->user());

        return back()->with('status', 'Solicitud enviada a revisión.');
    }

    public function assess(Request $request, SaleApplication $application, SaleApplicationService $service)
    {
        $this->scoped($request, $application);
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected', 'documents', 'changes'])],
            'down_payment' => ['nullable', 'decimal:0,2', 'min:0'], 'financing_charge' => ['nullable', 'decimal:0,2', 'min:0'],
            'installment_count' => ['nullable', 'integer', 'between:1,120'], 'frequency' => ['nullable', Rule::in(['daily', 'weekly', 'biweekly', 'monthly'])],
            'notes' => ['nullable', 'string', 'max:2000']]);
        if ($data['decision'] === 'documents') {
            $service->requestDocuments($application, $request->user(), $data['notes'] ?? 'Documentación requerida');
        } elseif ($data['decision'] === 'changes') {
            $service->requestChanges($application, $request->user(), $data['notes'] ?? 'Cambios requeridos');
        } else {
            $service->assess($application, $request->user(), $data['decision'] === 'approved', $data, $data['notes'] ?? null);
        }

        return back()->with('status', 'Evaluación registrada.');
    }

    public function uploadDocument(Request $request, SaleApplication $application, DocumentStorageService $storage)
    {
        $this->scoped($request, $application);
        Gate::authorize('sales.documents.manage');
        abort_unless($application->seller_id === $request->user()->id || Gate::allows('sales.view_all'), 403);
        $data = $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'], 'document_type' => ['required', Rule::in(['identity', 'address', 'income', 'other'])]]);
        $document = $storage->upload($request->attributes->get('activeTenant'), $request->user(), $data['file']);
        try {
            DB::transaction(function () use ($application, $document, $data, $request): void {
                $link = CustomerDocument::create(['tenant_id' => $application->tenant_id, 'customer_id' => $application->customer_id, 'sale_application_id' => $application->id,
                    'stored_document_id' => $document->id, 'document_type' => $data['document_type'], 'uploaded_by' => $request->user()->id, 'verification_status' => 'pending']);
                Audit::record('customer_document.uploaded', $link, $request->user(), $application->tenant_id);
            });
        } catch (\Throwable $error) {
            $storage->discardUnlinked($document);
            throw $error;
        }

        return back()->with('status', 'Documento cargado.');
    }

    public function customerDocuments(Request $request, Customer $customer)
    {
        $this->scoped($request, $customer);
        abort_unless(Gate::allows('sales.documents.manage') || Gate::allows('sales.documents.verify'), 403);
        $documents = CustomerDocument::forTenant($customer->tenant_id)->where('customer_id', $customer->id)
            ->when(! Gate::allows('sales.documents.verify'), fn ($q) => $q->where('uploaded_by', $request->user()->id))
            ->with('storedDocument')->latest()->get();

        return view('commercial.sales.customer-documents', compact('customer', 'documents'));
    }

    public function uploadCustomerDocument(Request $request, Customer $customer, DocumentStorageService $storage)
    {
        $this->scoped($request, $customer);
        Gate::authorize('sales.documents.manage');
        $data = $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'], 'document_type' => ['required', Rule::in(['identity', 'address', 'income', 'other'])]]);
        $stored = $storage->upload($request->attributes->get('activeTenant'), $request->user(), $data['file']);
        try {
            DB::transaction(function () use ($customer, $stored, $data, $request): void {
                $link = CustomerDocument::create(['tenant_id' => $customer->tenant_id, 'customer_id' => $customer->id, 'stored_document_id' => $stored->id,
                    'document_type' => $data['document_type'], 'uploaded_by' => $request->user()->id, 'verification_status' => 'pending']);
                Audit::record('customer_document.uploaded', $link, $request->user(), $customer->tenant_id);
            });
        } catch (\Throwable $error) {
            $storage->discardUnlinked($stored);
            throw $error;
        }

        return back()->with('status', 'Documento cargado.');
    }

    public function verifyDocument(Request $request, CustomerDocument $document)
    {
        $this->scoped($request, $document);
        Gate::authorize('verify', $document);
        $data = $request->validate(['status' => ['required', Rule::in(['verified', 'rejected'])], 'reason' => ['nullable', 'string', 'max:2000']]);
        abort_unless($document->verification_status === 'pending' && $document->uploaded_by !== $request->user()->id, 422);
        DB::transaction(function () use ($document, $data, $request): void {
            $document = CustomerDocument::forTenant($document->tenant_id)->whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless($document->verification_status === 'pending', 422);
            $document->update(['verification_status' => $data['status'], 'verified_by' => $request->user()->id, 'verified_at' => now(), 'rejection_reason' => $data['reason'] ?? null]);
            Audit::record('customer_document.'.$data['status'], $document, $request->user(), $document->tenant_id);
        });

        return back()->with('status', 'Documento revisado.');
    }

    public function document(Request $request, CustomerDocument $document, DocumentStorageService $storage)
    {
        $this->scoped($request, $document);
        Gate::authorize('view', $document);
        $extension = match ($document->storedDocument->mime_type) {
            'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', default => 'bin',
        };

        return response($storage->download($document->storedDocument))->header('Content-Type', $document->storedDocument->mime_type)
            ->header('Content-Disposition', 'attachment; filename="document-'.$document->id.'.'.$extension.'"');
    }

    public function confirm(Request $request, SaleApplication $application, ConfirmSaleAction $action)
    {
        $this->scoped($request, $application);
        $sale = $action->execute($application, $request->user());

        return redirect()->route('sales.show', $sale)->with('status', 'Venta confirmada.');
    }

    public function sales(Request $request)
    {
        Gate::authorize('sales.view');
        $sales = Sale::forTenant($request->attributes->get('activeTenant'))->with('customer')
            ->when(! Gate::allows('sales.view_all'), fn ($q) => $q->where('seller_id', $request->user()->id))->latest()->paginate(20);

        return view('commercial.sales.index', compact('sales'));
    }

    public function sale(Request $request, Sale $sale)
    {
        $this->scoped($request, $sale);
        Gate::authorize('view', $sale);
        $sale->load(['customer', 'items', 'plan.installments', 'accountEntries']);
        $documents = SaleDocument::forTenant($sale->tenant_id)->where('sale_id', $sale->id)->with('storedDocument')->get();

        return view('commercial.sales.show', compact('sale', 'documents'));
    }

    public function saleDocument(Request $request, SaleDocument $document, DocumentStorageService $storage)
    {
        $this->scoped($request, $document);
        Gate::authorize('sales.view');
        $sale = Sale::forTenant($document->tenant_id)->findOrFail($document->sale_id);
        Gate::authorize('view', $sale);

        return response($storage->download($document->storedDocument))->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$sale->sale_number.'-'.$document->document_type.'.pdf"');
    }

    public function cancel(Request $request, Sale $sale, CancelSaleAction $action)
    {
        $this->scoped($request, $sale);
        Gate::authorize('cancel', $sale);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $action->execute($sale, $request->user(), $data['reason']);

        return back()->with('status', 'Venta anulada.');
    }

    public function account(Request $request, Customer $customer, AccountStatementService $statement)
    {
        $this->scoped($request, $customer);
        Gate::authorize('sales.view');
        $sellerId = Gate::allows('sales.view_all') ? null : $request->user()->id;
        $entries = CustomerAccountEntry::forTenant($customer->tenant_id)->where('customer_id', $customer->id)
            ->when($sellerId, fn ($q) => $q->whereHas('sale', fn ($sale) => $sale->where('seller_id', $sellerId)))->orderBy('due_date')->get();
        $asOf = CarbonImmutable::today($request->attributes->get('activeTenant')->timezone);
        $balances = $statement->balances($customer, $asOf, $sellerId);
        $lateFees = $entries->mapWithKeys(fn ($entry) => [$entry->id => $statement->lateFee($entry, $asOf)]);

        return view('commercial.sales.account', compact('customer', 'entries', 'balances', 'lateFees'));
    }

    public function policies(Request $request)
    {
        Gate::authorize('sales.view');
        $policies = LateFeePolicy::forTenant($request->attributes->get('activeTenant'))->orderByDesc('version')->get();

        return view('commercial.sales.policies', compact('policies'));
    }

    public function storePolicy(Request $request, LateFeePolicyService $service)
    {
        $data = $request->validate(['effective_from' => ['required', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'grace_days' => ['required', 'integer', 'between:0,365'], 'daily_basis_points' => ['required', 'integer', 'between:0,10000'],
            'cap_basis_points' => ['required', 'integer', 'between:0,10000']]);
        $service->create($request->attributes->get('activeTenant'), $request->user(), $data);

        return back()->with('status', 'Política creada.');
    }
}
