<?php

namespace App\Livewire;

use App\Domain\Commercial\Decimal;
use App\Domain\Commercial\PriceService;
use App\Domain\Commercial\QuotationService;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\TenantMembership;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class QuotationBuilder extends Component
{
    public string $quoteId;

    public string $priceListId = '';

    public string $selectedProductId = '';

    public string $newQuantity = '1';

    public array $items = [];

    public string $saleModality = 'cash';

    public string $downPaymentAmount = '0.00';

    public string $installmentCount = '';

    public string $installmentFrequency = '';

    public string $validUntil = '';

    public string $notes = '';

    public string $internalNotes = '';

    public function boot(): void
    {
        abort_unless(auth()->check() && auth()->user()->status === 'active', 403);
        $tenantId = session('active_tenant_id');
        $membership = TenantMembership::with('tenant')->where('tenant_id', $tenantId)->where('user_id', auth()->id())->where('status', 'active')->first();
        abort_unless($membership && $membership->tenant->status === 'active', 403);
        request()->attributes->set('activeTenant', $membership->tenant);
        request()->attributes->set('activeMembership', $membership);
    }

    public function mount(Quotation $quotation): void
    {
        $this->quoteId = $quotation->id;
        $this->authorizeQuote();
        $this->priceListId = $quotation->price_list_id;
        $this->items = $quotation->items->map(fn ($item) => ['product_id' => $item->product_id, 'quantity' => (string) $item->quantity])->all();
        $this->saleModality = $quotation->sale_modality;
        $this->downPaymentAmount = (string) $quotation->down_payment_amount;
        $this->installmentCount = (string) ($quotation->installment_count ?? '');
        $this->installmentFrequency = $quotation->installment_frequency ?? '';
        $this->validUntil = $quotation->valid_until?->format('Y-m-d') ?? '';
        $this->notes = $quotation->notes ?? '';
        $this->internalNotes = $quotation->internal_notes ?? '';
    }

    public function addItem(): void
    {
        $this->authorizeQuote();
        $this->validate(['selectedProductId' => ['required', 'uuid'], 'newQuantity' => ['required', 'decimal:0,3', 'gt:0', 'max:10000']]);
        $product = Product::forTenant(request()->attributes->get('activeTenant'))->where('status', 'active')->findOrFail($this->selectedProductId);
        $list = PriceList::forTenant($product->tenant_id)->findOrFail($this->priceListId);
        app(PriceService::class)->applicable($product, $list, now());
        $this->items[] = ['product_id' => $product->id, 'quantity' => $this->newQuantity];
        $this->selectedProductId = '';
        $this->newQuantity = '1';
    }

    public function removeItem(int $index): void
    {
        $this->authorizeQuote();
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        $quote = $this->authorizeQuote();
        $data = $this->validatedData();
        app(QuotationService::class)->updateDraft($quote, auth()->user(), $data);
        session()->flash('status', 'Borrador guardado.');
        $this->redirect(route('quotations.show', $quote), navigate: false);
    }

    public function submit(): void
    {
        Gate::authorize('quotations.submit');
        $quote = $this->authorizeQuote();
        $data = $this->validatedData();
        app(QuotationService::class)->updateDraft($quote, auth()->user(), $data);
        app(QuotationService::class)->transition($quote, auth()->user(), 'pending_review');
        session()->flash('status', 'Presupuesto enviado a revisión.');
        $this->redirect(route('quotations.show', $quote), navigate: false);
    }

    public function render()
    {
        $tenantId = request()->attributes->get('activeTenant')->id;
        $products = Product::forTenant($tenantId)->where('status', 'active')->orderBy('name')->get();
        $lists = PriceList::forTenant($tenantId)->where('is_active', true)->where('type', '!=', 'internal')->when(! Gate::allows('products.manage_prices'), fn ($q) => $q->where('type', 'public'))->get();
        $preview = [];
        $total = 0;
        $list = $lists->firstWhere('id', $this->priceListId);
        if ($list) {
            foreach ($this->items as $item) {
                $product = $products->firstWhere('id', $item['product_id'] ?? null);
                if (! $product) {
                    continue;
                }
                try {
                    $price = app(PriceService::class)->applicable($product, $list, now());
                    $line = Decimal::line((string) ($item['quantity'] ?? '0'), $price->amount);
                    $total += $line;
                    $preview[] = ['product' => $product, 'quantity' => $item['quantity'], 'price' => $price->amount, 'line' => Decimal::format($line)];
                } catch (ValidationException $e) {
                    $preview[] = ['product' => $product, 'quantity' => $item['quantity'] ?? '', 'price' => null, 'line' => null];
                }
            }
        }

        return view('livewire.quotation-builder', compact('products', 'lists', 'preview', 'total'));
    }

    private function validatedData(): array
    {
        $data = $this->validate([
            'priceListId' => ['required', 'uuid'], 'saleModality' => ['required', Rule::in(['cash', 'financed'])],
            'downPaymentAmount' => ['required', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'installmentCount' => ['nullable', 'integer', 'between:1,120'], 'installmentFrequency' => ['nullable', Rule::in(['', 'weekly', 'biweekly', 'monthly'])],
            'validUntil' => ['nullable', 'date', 'after_or_equal:today'], 'notes' => ['nullable', 'string', 'max:5000'], 'internalNotes' => ['nullable', 'string', 'max:5000'],
            'items' => ['array', 'max:50'], 'items.*.product_id' => ['required', 'uuid'], 'items.*.quantity' => ['required', 'decimal:0,3', 'gt:0', 'max:10000'],
        ]);
        if ($data['saleModality'] === 'financed' && (! $data['installmentCount'] || ! $data['installmentFrequency'])) {
            throw ValidationException::withMessages(['installmentCount' => 'Definí cantidad y frecuencia de cuotas propuestas.']);
        }

        return [
            'price_list_id' => $data['priceListId'], 'sale_modality' => $data['saleModality'],
            'down_payment_amount' => $data['downPaymentAmount'], 'installment_count' => $data['installmentCount'],
            'installment_frequency' => $data['installmentFrequency'] ?: null, 'valid_until' => $data['validUntil'] ?: null,
            'notes' => $data['notes'], 'internal_notes' => $data['internalNotes'], 'items' => $data['items'],
        ];
    }

    private function authorizeQuote(): Quotation
    {
        Gate::authorize('quotations.update');
        $quote = Quotation::forTenant(request()->attributes->get('activeTenant'))->findOrFail($this->quoteId);
        Gate::authorize('view', $quote);
        abort_unless($quote->status === 'draft', 422);

        return $quote;
    }
}
