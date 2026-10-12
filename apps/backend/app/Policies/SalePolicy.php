<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantAuthorization;
use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function view(User $user, Sale $sale): bool
    {
        return $sale->tenant_id === request()->attributes->get('activeTenant')?->id
            && TenantAuthorization::allows(request(), $user, 'sales.view')
            && (TenantAuthorization::allows(request(), $user, 'sales.view_all') || $sale->seller_id === $user->id);
    }

    public function cancel(User $user, Sale $sale): bool
    {
        return $this->view($user, $sale) && TenantAuthorization::allows(request(), $user, 'sales.cancel');
    }
}
