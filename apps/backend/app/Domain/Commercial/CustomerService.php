<?php

namespace App\Domain\Commercial;

use App\Domain\Tenancy\Audit;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerService
{
    public function create(Tenant $tenant, User $actor, array $data): Customer
    {
        try {
            return DB::transaction(function () use ($tenant, $actor, $data): Customer {
                $customer = Customer::create(array_merge($data, [
                    'tenant_id' => $tenant->id,
                    'customer_number' => CommercialNumber::next($tenant->id, 'customer', 'CLI-'),
                    'document_normalized' => $this->normalizeDocument($data['document_number'] ?? null),
                    'created_by' => $actor->id,
                ]));
                Audit::record('customer.created', $customer, $actor, $tenant->id);

                return $customer;
            });
        } catch (QueryException $e) {
            $this->handleUniqueDocument($e);
            throw $e;
        }
    }

    public function update(Customer $customer, User $actor, array $data): void
    {
        try {
            DB::transaction(function () use ($customer, $actor, $data): void {
                $customer->update(array_merge($data, [
                    'document_normalized' => $this->normalizeDocument($data['document_number'] ?? $customer->document_number),
                ]));
                Audit::record('customer.updated', $customer, $actor, $customer->tenant_id);
            });
        } catch (QueryException $e) {
            $this->handleUniqueDocument($e);
            throw $e;
        }
    }

    public function saveAddress(Customer $customer, User $actor, array $data, ?CustomerAddress $address = null): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $actor, $data, $address): CustomerAddress {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            if ($data['is_primary'] ?? false) {
                CustomerAddress::forTenant($customer->tenant_id)->where('customer_id', $customer->id)->update(['is_primary' => false]);
            }
            if ($address) {
                $address->update($data);
            } else {
                $address = CustomerAddress::create(array_merge($data, ['tenant_id' => $customer->tenant_id, 'customer_id' => $customer->id]));
            }
            Audit::record('customer.address_saved', $address, $actor, $customer->tenant_id);

            return $address;
        });
    }

    public function deleteAddress(CustomerAddress $address, User $actor): void
    {
        DB::transaction(function () use ($address, $actor): void {
            $address->delete();
            Audit::record('customer.address_deleted', $address, $actor, $address->tenant_id);
        });
    }

    private function handleUniqueDocument(QueryException $e): void
    {
        if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'customers_document_unique')) {
            throw ValidationException::withMessages(['document_number' => 'Ya existe un cliente con este tipo y número de documento.']);
        }
    }

    private function normalizeDocument(?string $document): ?string
    {
        return $document ? preg_replace('/[^A-Z0-9]/', '', strtoupper($document)) : null;
    }
}
