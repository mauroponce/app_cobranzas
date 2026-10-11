<?php

namespace App\Http\Controllers;

use App\Domain\Commercial\CustomerService;
use App\Domain\Tenancy\Audit;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('customers.view');
        $tenant = $request->attributes->get('activeTenant');
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive', 'blocked'])]]);
        $customers = Customer::forTenant($tenant)->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('first_name', 'ilike', "%$search%")->orWhere('last_name', 'ilike', "%$search%")
                    ->orWhere('customer_number', 'ilike', "%$search%")->orWhere('document_normalized', 'ilike', '%'.preg_replace('/[^A-Z0-9]/', '', strtoupper($search)).'%');
            });
        })->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->orderBy('last_name')->paginate(15)->withQueryString();

        return view('commercial.customers.index', compact('customers', 'filters'));
    }

    public function create()
    {
        Gate::authorize('customers.create');

        return view('commercial.customers.form', ['customer' => null]);
    }

    public function store(Request $request, CustomerService $service): RedirectResponse
    {
        Gate::authorize('customers.create');
        $customer = $service->create($request->attributes->get('activeTenant'), $request->user(), $this->validated($request));

        return redirect()->route('customers.show', $customer)->with('status', 'Cliente creado.');
    }

    public function show(Request $request, Customer $customer)
    {
        Gate::authorize('customers.view');
        $this->assertTenant($request, $customer);
        Gate::authorize('view', $customer);
        $customer->load('addresses');
        if (Gate::allows('quotations.view')) {
            $customer->load(['quotations' => fn ($query) => $query->when(! Gate::allows('quotations.view_all'), fn ($query) => $query->where('seller_id', $request->user()->id))]);
        } else {
            $customer->setRelation('quotations', collect());
        }

        return view('commercial.customers.show', compact('customer'));
    }

    public function edit(Request $request, Customer $customer)
    {
        Gate::authorize('customers.update');
        $this->assertTenant($request, $customer);
        Gate::authorize('update', $customer);

        return view('commercial.customers.form', compact('customer'));
    }

    public function update(Request $request, Customer $customer, CustomerService $service): RedirectResponse
    {
        Gate::authorize('customers.update');
        $this->assertTenant($request, $customer);
        Gate::authorize('update', $customer);
        $service->update($customer, $request->user(), $this->validated($request));

        return redirect()->route('customers.show', $customer)->with('status', 'Cliente actualizado.');
    }

    public function disable(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('customers.disable');
        $this->assertTenant($request, $customer);
        $request->validate(['status' => ['required', Rule::in(['active', 'inactive', 'blocked'])]]);
        DB::transaction(function () use ($customer, $request): void {
            $customer->update(['status' => (string) $request->string('status')]);
            Audit::record('customer.status_changed', $customer, $request->user(), $customer->tenant_id, ['status' => $customer->status]);
        });

        return back()->with('status', 'Estado actualizado.');
    }

    public function address(Request $request, Customer $customer, CustomerService $service): RedirectResponse
    {
        Gate::authorize('customers.update');
        $this->assertTenant($request, $customer);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'], 'street' => ['required', 'string', 'max:255'],
            'street_number' => ['required', 'string', 'max:30'], 'floor' => ['nullable', 'string', 'max:30'],
            'unit' => ['nullable', 'string', 'max:30'], 'neighborhood' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'], 'province' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'], 'reference' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_primary' => ['boolean'],
        ]);
        $data['is_primary'] = $request->boolean('is_primary');
        $service->saveAddress($customer, $request->user(), $data);

        return back()->with('status', 'Domicilio guardado.');
    }

    public function updateAddress(Request $request, Customer $customer, CustomerAddress $address, CustomerService $service): RedirectResponse
    {
        Gate::authorize('customers.update');
        $this->assertTenant($request, $customer);
        abort_unless($address->tenant_id === $customer->tenant_id && $address->customer_id === $customer->id, 404);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'], 'street' => ['required', 'string', 'max:255'], 'street_number' => ['required', 'string', 'max:30'],
            'floor' => ['nullable', 'string', 'max:30'], 'unit' => ['nullable', 'string', 'max:30'], 'neighborhood' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'], 'province' => ['required', 'string', 'max:255'], 'postal_code' => ['nullable', 'string', 'max:20'],
            'reference' => ['nullable', 'string', 'max:2000'], 'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'], 'is_primary' => ['boolean'],
        ]);
        $data['is_primary'] = $request->boolean('is_primary');
        $service->saveAddress($customer, $request->user(), $data, $address);

        return back()->with('status', 'Domicilio actualizado.');
    }

    public function deleteAddress(Request $request, Customer $customer, CustomerAddress $address, CustomerService $service): RedirectResponse
    {
        Gate::authorize('customers.update');
        $this->assertTenant($request, $customer);
        abort_unless($address->tenant_id === $customer->tenant_id && $address->customer_id === $customer->id, 404);
        $service->deleteAddress($address, $request->user());

        return back()->with('status', 'Domicilio eliminado.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'], 'last_name' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', Rule::in(['DNI', 'CUIT', 'CUIL', 'OTHER'])],
            'document_number' => ['nullable', 'string', 'max:32', 'regex:/[A-Za-z0-9]/'], 'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'], 'whatsapp_phone' => ['nullable', 'string', 'max:32'],
            'birth_date' => ['nullable', 'date', 'before:today'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if (filled($data['document_type'] ?? null) !== filled($data['document_number'] ?? null)) {
            throw ValidationException::withMessages(['document_number' => 'Completá tipo y número de documento juntos.']);
        }

        return $data;
    }

    private function assertTenant(Request $request, Customer $customer): void
    {
        abort_unless($customer->tenant_id === $request->attributes->get('activeTenant')->id, 404);
    }
}
