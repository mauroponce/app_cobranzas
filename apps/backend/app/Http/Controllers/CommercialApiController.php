<?php

namespace App\Http\Controllers;

use App\Domain\Collections\CollectionPortfolioService;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Quotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommercialApiController extends Controller
{
    public function customers(Request $request, CollectionPortfolioService $portfolio): JsonResponse
    {
        Gate::authorize('customers.view');
        $tenant = $request->attributes->get('activeTenant');
        $membership = $request->attributes->get('activeMembership');
        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? null;
        $page = $portfolio->customers($tenant, $membership)
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('first_name', 'ilike', "%$search%")->orWhere('last_name', 'ilike', "%$search%")))->orderBy('last_name')->paginate(20);

        return response()->json(['data' => $page->getCollection()->map(fn ($customer) => $this->customerData($customer)), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()]]);
    }

    public function customer(Request $request, Customer $customer, CollectionPortfolioService $portfolio): JsonResponse
    {
        Gate::authorize('customers.view');
        $tenant = $request->attributes->get('activeTenant');
        abort_unless($customer->tenant_id === $tenant->id, 404);
        abort_unless($portfolio->canViewCustomer($tenant, $request->attributes->get('activeMembership'), $customer), 403);

        return response()->json(['data' => $this->customerData($customer)]);
    }

    public function addresses(Request $request, Customer $customer, CollectionPortfolioService $portfolio): JsonResponse
    {
        Gate::authorize('customers.view');
        $tenant = $request->attributes->get('activeTenant');
        abort_unless($customer->tenant_id === $tenant->id, 404);
        abort_unless($portfolio->canViewCustomer($tenant, $request->attributes->get('activeMembership'), $customer), 403);
        $addresses = CustomerAddress::forTenant($customer->tenant_id)->where('customer_id', $customer->id)->orderByDesc('is_primary')->get()
            ->map(fn ($address) => $address->only(['id', 'label', 'street', 'street_number', 'floor', 'unit', 'neighborhood', 'city', 'province', 'postal_code', 'country', 'reference', 'latitude', 'longitude', 'is_primary']));

        return response()->json(['data' => $addresses]);
    }

    public function products(Request $request): JsonResponse
    {
        Gate::authorize('products.view');
        $tenant = $request->attributes->get('activeTenant');
        $page = Product::forTenant($tenant)->where('status', 'active')->orderBy('name')->paginate(20);
        $lists = PriceList::forTenant($tenant)->where('is_active', true)->where('type', 'public')->get();
        $prices = ProductPrice::forTenant($tenant)->whereIn('price_list_id', $lists->pluck('id'))->whereIn('product_id', $page->pluck('id'))
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', today()))->get()->groupBy('product_id');
        $data = $page->getCollection()->map(fn ($product) => $this->productData($product, $prices->get($product->id, collect()), $lists));

        return response()->json(['data' => $data, 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()]]);
    }

    public function product(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('products.view');
        abort_unless($product->tenant_id === $request->attributes->get('activeTenant')->id && $product->status === 'active', 404);
        $lists = PriceList::forTenant($product->tenant_id)->where('is_active', true)->where('type', 'public')->get();
        $prices = ProductPrice::forTenant($product->tenant_id)->where('product_id', $product->id)->whereIn('price_list_id', $lists->pluck('id'))
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', today()))->get();

        return response()->json(['data' => $this->productData($product, $prices, $lists)]);
    }

    public function quotations(Request $request): JsonResponse
    {
        Gate::authorize('quotations.view');
        $page = Quotation::forTenant($request->attributes->get('activeTenant'))->when(! Gate::allows('quotations.view_all'), fn ($q) => $q->where('seller_id', $request->user()->id))->latest()->paginate(20);

        return response()->json(['data' => $page->getCollection()->map(fn ($quote) => $this->quotationData($quote)), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()]]);
    }

    public function quotation(Request $request, Quotation $quotation): JsonResponse
    {
        Gate::authorize('quotations.view');
        abort_unless($quotation->tenant_id === $request->attributes->get('activeTenant')->id, 404);
        abort_unless(Gate::allows('quotations.view_all') || $quotation->seller_id === $request->user()->id, 403);
        $quotation->load('items');
        $data = $this->quotationData($quotation);
        $data['items'] = $quotation->items->map(fn ($item) => $item->only(['id', 'description', 'sku_snapshot', 'quantity', 'unit_price', 'discount_amount', 'line_total', 'position']));

        return response()->json(['data' => $data]);
    }

    private function customerData(Customer $customer): array
    {
        return $customer->only(['id', 'customer_number', 'first_name', 'last_name', 'document_type', 'document_number', 'email', 'phone', 'whatsapp_phone', 'status']);
    }

    private function productData(Product $product, $prices, $lists): array
    {
        return array_merge($product->only(['id', 'sku', 'name', 'description', 'brand', 'unit']), [
            'prices' => $prices->map(fn ($price) => ['price_list' => $lists->firstWhere('id', $price->price_list_id)?->only(['id', 'name', 'code']), 'amount' => $price->amount])->values(),
        ]);
    }

    private function quotationData(Quotation $quote): array
    {
        return $quote->only(['id', 'quotation_number', 'customer_id', 'seller_id', 'status', 'currency', 'sale_modality', 'subtotal', 'discount_amount', 'total_amount', 'down_payment_amount', 'financed_amount', 'installment_count', 'installment_frequency', 'estimated_installment_amount', 'valid_until', 'notes', 'created_at']);
    }
}
