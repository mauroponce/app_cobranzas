<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantAuthorization;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        return $customer->tenant_id === request()->attributes->get('activeTenant')?->id
            && TenantAuthorization::allows(request(), $user, 'customers.view');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $customer->tenant_id === request()->attributes->get('activeTenant')?->id
            && TenantAuthorization::allows(request(), $user, 'customers.update');
    }
}
