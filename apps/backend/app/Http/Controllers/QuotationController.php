<?php

namespace App\Http\Controllers;

use App\Domain\Commercial\QuotationService;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\TenantMembership;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('quotations.view');
        $tenant = $request->attributes->get('activeTenant');
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['draft', 'pending_review', 'approved', 'sent', 'accepted', 'rejected', 'expired', 'cancelled'])], 'seller_id' => ['nullable', 'uuid']]);
        $quotes = Quotation::forTenant($tenant)->with(['customer', 'seller'])
            ->when(! Gate::allows('quotations.view_all'), fn ($q) => $q->where('seller_id', $request->user()->id))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['seller_id'] ?? null, fn ($q, $v) => $q->where('seller_id', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('quotation_number', 'ilike', "%$v%")->orWhereHas('customer', fn ($q) => $q->where('first_name', 'ilike', "%$v%")->orWhere('last_name', 'ilike', "%$v%"))))
            ->latest()->paginate(15)->withQueryString();
        $sellers = Gate::allows('quotations.view_all') ? TenantMembership::forTenant($tenant)->where('status', 'active')->with('user')->get() : collect();

        return view('commercial.quotations.index', compact('quotes', 'filters', 'sellers'));
    }

    public function create(Request $request)
    {
        Gate::authorize('quotations.create');
        $tenant = $request->attributes->get('activeTenant');
        $customers = Customer::forTenant($tenant)->where('status', 'active')->orderBy('last_name')->get();
        $lists = $this->availableLists($request);

        return view('commercial.quotations.create', compact('customers', 'lists'));
    }

    public function store(Request $request, QuotationService $service): RedirectResponse
    {
        Gate::authorize('quotations.create');
        $data = $request->validate(['customer_id' => ['required', 'uuid'], 'price_list_id' => ['required', 'uuid'], 'address_id' => ['nullable', 'uuid']]);
        $quote = $service->create($request->attributes->get('activeTenant'), $request->user(), $data);

        return redirect()->route('quotations.edit', $quote)->with('status', 'Borrador creado.');
    }

    public function show(Request $request, Quotation $quotation)
    {
        $this->authorizeQuote($request, $quotation);
        $quotation->load(['customer', 'seller', 'priceList', 'items', 'statusHistory', 'activities']);

        return view('commercial.quotations.show', compact('quotation'));
    }

    public function edit(Request $request, Quotation $quotation)
    {
        $this->authorizeQuote($request, $quotation, 'quotations.update');
        abort_unless($quotation->status === 'draft', 422);
        $quotation->load('items');
        $products = Product::forTenant($quotation->tenant_id)->where('status', 'active')->orderBy('name')->get();
        $lists = $this->availableLists($request);

        return view('commercial.quotations.edit', compact('quotation', 'products', 'lists'));
    }

    public function update(Request $request, Quotation $quotation, QuotationService $service): RedirectResponse
    {
        $this->authorizeQuote($request, $quotation, 'quotations.update');
        $data = $request->validate([
            'price_list_id' => ['required', 'uuid'], 'notes' => ['nullable', 'string', 'max:5000'], 'internal_notes' => ['nullable', 'string', 'max:5000'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'], 'sale_modality' => ['required', Rule::in(['cash', 'financed'])],
            'down_payment_amount' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'installment_count' => ['nullable', 'integer', 'between:1,120'], 'installment_frequency' => ['nullable', Rule::in(['weekly', 'biweekly', 'monthly'])],
            'items' => ['array', 'max:50'], 'items.*.product_id' => ['required', 'uuid'], 'items.*.quantity' => ['required', 'decimal:0,3', 'gt:0', 'max:10000'],
        ]);
        if ($data['sale_modality'] === 'financed' && (empty($data['installment_count']) || empty($data['installment_frequency']))) {
            throw ValidationException::withMessages(['installment_count' => 'Definí cantidad y frecuencia de cuotas propuestas.']);
        }
        $service->updateDraft($quotation, $request->user(), $data);

        return redirect()->route('quotations.show', $quotation)->with('status', 'Borrador guardado.');
    }

    public function transition(Request $request, Quotation $quotation, QuotationService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending_review', 'approved', 'sent', 'accepted', 'rejected', 'expired', 'cancelled'])], 'reason' => ['nullable', 'string', 'max:2000']]);
        $capability = match ($data['status']) {
            'pending_review' => 'quotations.submit', 'approved' => 'quotations.approve', 'rejected' => 'quotations.reject',
            'sent', 'accepted' => 'quotations.send', 'expired', 'cancelled' => 'quotations.cancel',
        };
        $this->authorizeQuote($request, $quotation, $capability);
        $service->transition($quotation, $request->user(), $data['status'], $data['reason'] ?? null);

        return back()->with('status', 'Estado actualizado.');
    }

    public function duplicate(Request $request, Quotation $quotation, QuotationService $service): RedirectResponse
    {
        $this->authorizeQuote($request, $quotation, 'quotations.create');
        $copy = $service->duplicate($quotation, $request->user());

        return redirect()->route('quotations.edit', $copy)->with('status', 'Se creó un nuevo borrador.');
    }

    public function activity(Request $request, Quotation $quotation, QuotationService $service): RedirectResponse
    {
        $this->authorizeQuote($request, $quotation, 'quotations.manage_activities');
        $data = $request->validate(['type' => ['required', Rule::in(['call', 'whatsapp', 'interested', 'changes_requested', 'no_response', 'follow_up', 'rejected', 'note'])], 'notes' => ['required', 'string', 'max:5000'], 'next_follow_up_at' => ['nullable', 'date', 'after:now']]);
        $service->activity($quotation, $request->user(), $data);

        return back()->with('status', 'Actividad registrada.');
    }

    public function print(Request $request, Quotation $quotation)
    {
        $this->authorizeQuote($request, $quotation, 'quotations.export');
        $quotation->load(['items', 'seller']);

        return view('commercial.quotations.print', compact('quotation'));
    }

    public function pdf(Request $request, Quotation $quotation)
    {
        $this->authorizeQuote($request, $quotation, 'quotations.export');
        abort_if($quotation->status === 'draft' || $quotation->status === 'pending_review', 422, 'El PDF requiere un presupuesto aprobado o emitido.');
        $quotation->load(['items', 'seller']);
        $pdf = Pdf::loadView('commercial.quotations.print', compact('quotation'));

        return $pdf->stream($quotation->quotation_number.'.pdf');
    }

    private function availableLists(Request $request)
    {
        return PriceList::forTenant($request->attributes->get('activeTenant'))->where('is_active', true)->where('type', '!=', 'internal')
            ->when(! Gate::allows('products.manage_prices'), fn ($q) => $q->where('type', 'public'))->orderByDesc('is_default')->get();
    }

    private function authorizeQuote(Request $request, Quotation $quotation, string $capability = 'quotations.view'): void
    {
        Gate::authorize($capability);
        abort_unless($quotation->tenant_id === $request->attributes->get('activeTenant')->id, 404);
        Gate::authorize('view', $quotation);
    }
}
