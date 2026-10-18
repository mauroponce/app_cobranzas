<?php

namespace App\Domain\Collections;

use App\Domain\Tenancy\AuthorizationService;
use App\Models\Customer;
use App\Models\CustomerCollectorAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Database\Eloquent\Builder;

final class CollectionPortfolioService
{
    public function __construct(
        private AuthorizationService $authorization,
        private CollectionAssignmentScope $assignmentScope,
    ) {}

    /** @return Builder<Customer> */
    public function customers(Tenant $tenant, TenantMembership $membership, string $capability = 'customers.view'): Builder
    {
        $decision = $this->authorization->decision($membership, $capability);
        abort_unless($decision['allowed'], 403);

        return $this->scopeCustomers(Customer::forTenant($tenant), $tenant, $membership, $decision['scope']);
    }

    public function canViewCustomer(Tenant $tenant, TenantMembership $membership, Customer $customer, string $capability = 'customers.view'): bool
    {
        if ($customer->tenant_id !== $tenant->id) {
            return false;
        }

        $decision = $this->authorization->decision($membership, $capability);
        if (! $decision['allowed']) {
            return false;
        }

        return $this->scopeCustomers(Customer::forTenant($tenant), $tenant, $membership, $decision['scope'])
            ->whereKey($customer->id)
            ->exists();
    }

    /** @param Builder<Customer> $query
     * @return Builder<Customer>
     */
    private function scopeCustomers(Builder $query, Tenant $tenant, TenantMembership $membership, ?string $scope): Builder
    {
        if ($scope === 'all') {
            return $query;
        }

        if ($scope === 'own') {
            return $query->whereHas('sales', fn (Builder $sales) => $sales->where('seller_id', $membership->user_id));
        }

        abort_unless(in_array($scope, ['assigned', 'team'], true), 403);

        return $query->whereIn('customers.id', $this->customerIds($tenant, $membership, $scope));
    }

    public function canCollect(Tenant $tenant, TenantMembership $membership, Customer $customer): bool
    {
        if ($customer->tenant_id !== $tenant->id) {
            return false;
        }
        $decision = $this->authorization->decision($membership, 'payments.create');
        if (! $decision['allowed']) {
            return false;
        }
        if ($decision['scope'] === 'all') {
            return true;
        }
        if (! in_array($decision['scope'], ['assigned', 'team'], true)) {
            return false;
        }

        return $this->customerIds($tenant, $membership, $decision['scope'])->where('customer_id', $customer->id)->exists();
    }

    public function eligibleCollector(Tenant $tenant, TenantMembership $membership): bool
    {
        return $membership->tenant_id === $tenant->id
            && $membership->status === 'active'
            && $this->authorization->allows($membership, 'payments.create');
    }

    private function customerIds(Tenant $tenant, TenantMembership $membership, string $scope)
    {
        $assignments = CustomerCollectorAssignment::query()
            ->where('customer_collector_assignments.tenant_id', $tenant->id)
            ->whereNull('customer_collector_assignments.unassigned_at');

        if ($scope === 'assigned') {
            return $assignments->where('customer_collector_assignments.collector_membership_id', $membership->id)
                ->select('customer_collector_assignments.customer_id');
        }

        return $assignments
            ->whereIn('customer_collector_assignments.collector_membership_id', $this->assignmentScope->membershipIds($tenant, $membership, $scope))
            ->select('customer_collector_assignments.customer_id');
    }
}
