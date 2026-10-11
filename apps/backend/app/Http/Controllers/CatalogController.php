<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\Audit;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductCost;
use App\Models\ProductPrice;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('products.view');
        $tenant = $request->attributes->get('activeTenant');
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])], 'category_id' => ['nullable', 'uuid']]);
        $products = Product::forTenant($tenant)->with('category')->when(! Gate::allows('products.update'), fn ($q) => $q->where('status', 'active'))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('name', 'ilike', "%$v%")->orWhere('sku', 'ilike', "%$v%")))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))->orderBy('name')->paginate(15)->withQueryString();
        $categories = ProductCategory::forTenant($tenant)->orderBy('name')->get();
        $lists = PriceList::forTenant($tenant)->where('is_active', true)->when(! Gate::allows('products.manage_prices'), fn ($q) => $q->where('type', 'public'))->get();
        $prices = ProductPrice::forTenant($tenant)->whereIn('price_list_id', $lists->pluck('id'))->whereIn('product_id', $products->pluck('id'))
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', today()))->get()->groupBy('product_id');

        $allLists = Gate::allows('products.manage_prices') ? PriceList::forTenant($tenant)->orderBy('name')->get() : collect();

        return view('commercial.catalog.index', compact('products', 'categories', 'lists', 'allLists', 'prices', 'filters'));
    }

    public function show(Request $request, Product $product)
    {
        Gate::authorize('products.view');
        $this->assertTenant($request, $product);
        abort_if($product->status !== 'active' && ! Gate::allows('products.update'), 404);
        Gate::authorize('view', $product);
        $lists = PriceList::forTenant($product->tenant_id)->where('is_active', true)->when(! Gate::allows('products.manage_prices'), fn ($q) => $q->where('type', 'public'))->get();
        $prices = ProductPrice::forTenant($product->tenant_id)->where('product_id', $product->id)->whereIn('price_list_id', $lists->pluck('id'))->get();
        $costs = Gate::allows('products.view_costs') ? ProductCost::forTenant($product->tenant_id)->where('product_id', $product->id)->get() : collect();

        return view('commercial.catalog.show', compact('product', 'lists', 'prices', 'costs'));
    }

    public function create(Request $request)
    {
        Gate::authorize('products.create');
        $categories = ProductCategory::forTenant($request->attributes->get('activeTenant'))->orderBy('name')->get();

        return view('commercial.catalog.form', ['product' => null, 'categories' => $categories]);
    }

    public function edit(Request $request, Product $product)
    {
        Gate::authorize('products.update');
        $this->assertTenant($request, $product);
        Gate::authorize('update', $product);
        $categories = ProductCategory::forTenant($product->tenant_id)->orderBy('name')->get();

        return view('commercial.catalog.form', compact('product', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('products.create');
        $tenant = $request->attributes->get('activeTenant');
        $data = $this->productData($request, $tenant->id);
        $product = DB::transaction(function () use ($tenant, $request, $data) {
            $product = Product::create(array_merge($data, ['tenant_id' => $tenant->id]));
            Audit::record('product.created', $product, $request->user(), $tenant->id);

            return $product;
        });

        return redirect()->route('products.show', $product)->with('status', 'Producto creado.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('products.update');
        $this->assertTenant($request, $product);
        Gate::authorize('update', $product);
        $data = $this->productData($request, $product->tenant_id);
        DB::transaction(function () use ($request, $product, $data): void {
            $wasActive = $product->status === 'active';
            $product->update($data);
            if ($wasActive && $product->status === 'inactive') {
                Audit::record('product.deactivated', $product, $request->user(), $product->tenant_id);
            }
            Audit::record('product.updated', $product, $request->user(), $product->tenant_id);
        });

        return redirect()->route('products.show', $product)->with('status', 'Producto actualizado.');
    }

    public function category(Request $request): RedirectResponse
    {
        Gate::authorize('products.update');
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $tenant = $request->attributes->get('activeTenant');
        $category = ProductCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $data['name']]);
        if ($category->wasRecentlyCreated) {
            Audit::record('product.category_created', $category, $request->user(), $tenant->id);
        }

        return back()->with('status', 'Categoría guardada.');
    }

    public function updateCategory(Request $request, ProductCategory $category): RedirectResponse
    {
        Gate::authorize('products.update');
        abort_unless($category->tenant_id === $request->attributes->get('activeTenant')->id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($request, $category, $data): void {
            $category->update($data);
            Audit::record('product.category_updated', $category, $request->user(), $category->tenant_id);
        });

        return back()->with('status', 'Categoría actualizada.');
    }

    public function list(Request $request): RedirectResponse
    {
        Gate::authorize('products.manage_prices');
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:50'], 'type' => ['required', Rule::in(['public', 'wholesale', 'internal'])], 'is_default' => ['boolean']]);
        $tenant = $request->attributes->get('activeTenant');
        DB::transaction(function () use ($request, $tenant, $data): void {
            if ($data['is_default'] ?? false) {
                PriceList::forTenant($tenant)->update(['is_default' => false]);
            }
            $list = PriceList::create(array_merge($data, ['tenant_id' => $tenant->id, 'currency' => 'ARS', 'is_active' => true]));
            Audit::record('price_list.created', $list, $request->user(), $tenant->id);
        });

        return back()->with('status', 'Lista creada.');
    }

    public function updateList(Request $request, PriceList $list): RedirectResponse
    {
        Gate::authorize('products.manage_prices');
        abort_unless($list->tenant_id === $request->attributes->get('activeTenant')->id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::in(['public', 'wholesale', 'internal'])], 'is_active' => ['boolean'], 'is_default' => ['boolean']]);
        DB::transaction(function () use ($request, $list, $data): void {
            PriceList::forTenant($list->tenant_id)->whereKey($list->id)->lockForUpdate()->firstOrFail();
            if ($data['is_default'] ?? false) {
                PriceList::forTenant($list->tenant_id)->update(['is_default' => false]);
            }
            $list->update(['name' => $data['name'], 'type' => $data['type'], 'is_active' => $data['is_active'] ?? false, 'is_default' => ($data['is_default'] ?? false) && ($data['is_active'] ?? false)]);
            Audit::record('price_list.updated', $list, $request->user(), $list->tenant_id);
        });

        return back()->with('status', 'Lista actualizada.');
    }

    public function price(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('products.manage_prices');
        $this->assertTenant($request, $product);
        $data = $request->validate(['price_list_id' => ['required', 'uuid'], 'amount' => ['required', 'decimal:0,2', 'min:0', 'max:999999999.99'], 'valid_from' => ['nullable', 'date'], 'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from']]);
        PriceList::forTenant($product->tenant_id)->findOrFail($data['price_list_id']);
        try {
            DB::transaction(function () use ($product, $request, $data): void {
                $price = ProductPrice::create(array_merge($data, ['tenant_id' => $product->tenant_id, 'product_id' => $product->id]));
                Audit::record('product.price_changed', $price, $request->user(), $product->tenant_id, ['amount' => $price->amount]);
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01') {
                throw ValidationException::withMessages(['valid_from' => 'El período se superpone con otro precio.']);
            }
            throw $e;
        }

        return back()->with('status', 'Precio guardado.');
    }

    public function cost(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('products.manage_costs');
        $this->assertTenant($request, $product);
        $data = $request->validate(['amount' => ['required', 'decimal:0,2', 'min:0', 'max:999999999.99'], 'valid_from' => ['nullable', 'date'], 'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from']]);
        try {
            DB::transaction(function () use ($product, $request, $data): void {
                $cost = ProductCost::create(array_merge($data, ['tenant_id' => $product->tenant_id, 'product_id' => $product->id]));
                Audit::record('product.cost_changed', $cost, $request->user(), $product->tenant_id);
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01') {
                throw ValidationException::withMessages(['valid_from' => 'El período se superpone con otro costo.']);
            }
            throw $e;
        }

        return back()->with('status', 'Costo guardado.');
    }

    public function closePrice(Request $request, Product $product, ProductPrice $price): RedirectResponse
    {
        Gate::authorize('products.manage_prices');
        $this->assertTenant($request, $product);
        abort_unless($price->tenant_id === $product->tenant_id && $price->product_id === $product->id, 404);
        $data = $request->validate(['valid_until' => ['required', 'date']]);
        try {
            DB::transaction(function () use ($product, $price, $request, $data): void {
                $price = ProductPrice::forTenant($product->tenant_id)->whereKey($price->id)->lockForUpdate()->firstOrFail();
                if ($price->valid_from && $data['valid_until'] < $price->valid_from->toDateString()) {
                    throw ValidationException::withMessages(['valid_until' => 'La fecha final es anterior al inicio.']);
                }
                $price->update(['valid_until' => $data['valid_until']]);
                Audit::record('product.price_closed', $price, $request->user(), $product->tenant_id, ['valid_until' => $data['valid_until']]);
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01') {
                throw ValidationException::withMessages(['valid_until' => 'La vigencia se superpone con otro precio.']);
            }
            throw $e;
        }

        return back()->with('status', 'Vigencia de precio cerrada.');
    }

    public function closeCost(Request $request, Product $product, ProductCost $cost): RedirectResponse
    {
        Gate::authorize('products.manage_costs');
        $this->assertTenant($request, $product);
        abort_unless($cost->tenant_id === $product->tenant_id && $cost->product_id === $product->id, 404);
        $data = $request->validate(['valid_until' => ['required', 'date']]);
        try {
            DB::transaction(function () use ($product, $cost, $request, $data): void {
                $cost = ProductCost::forTenant($product->tenant_id)->whereKey($cost->id)->lockForUpdate()->firstOrFail();
                if ($cost->valid_from && $data['valid_until'] < $cost->valid_from->toDateString()) {
                    throw ValidationException::withMessages(['valid_until' => 'La fecha final es anterior al inicio.']);
                }
                $cost->update(['valid_until' => $data['valid_until']]);
                Audit::record('product.cost_closed', $cost, $request->user(), $product->tenant_id, ['valid_until' => $data['valid_until']]);
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01') {
                throw ValidationException::withMessages(['valid_until' => 'La vigencia se superpone con otro costo.']);
            }
            throw $e;
        }

        return back()->with('status', 'Vigencia de costo cerrada.');
    }

    private function productData(Request $request, string $tenantId): array
    {
        $data = $request->validate(['sku' => ['required', 'string', 'max:255'], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'category_id' => ['nullable', 'uuid'], 'brand' => ['nullable', 'string', 'max:255'], 'unit' => ['required', 'string', 'max:30'], 'status' => ['required', Rule::in(['active', 'inactive'])]]);
        if ($data['category_id'] ?? null) {
            ProductCategory::forTenant($tenantId)->findOrFail($data['category_id']);
        }

        return $data;
    }

    private function assertTenant(Request $request, Product $product): void
    {
        abort_unless($product->tenant_id === $request->attributes->get('activeTenant')->id, 404);
    }
}
