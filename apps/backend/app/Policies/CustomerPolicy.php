<?php

namespace App\Policies;

use App\Domain\Collections\CollectionPortfolioService;
use App\Domain\Tenancy\TenantAuthorization;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        $tenant = request()->attributes->get('activeTenant');
        $membership = TenantAuthorization::membership(request(), $user);

        return $tenant !== null && $membership !== null
            && app(CollectionPortfolioService::class)->canViewCustomer($tenant, $membership, $customer);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $customer->tenant_id === request()->attributes->get('activeTenant')?->id
            && TenantAuthorization::allows(request(), $user, 'customers.update');
    }
}
