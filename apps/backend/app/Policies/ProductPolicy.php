<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantAuthorization;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function view(User $user, Product $product): bool
    {
        return $product->tenant_id === request()->attributes->get('activeTenant')?->id
            && ($product->status === 'active' || TenantAuthorization::allows(request(), $user, 'products.update'))
            && TenantAuthorization::allows(request(), $user, 'products.view');
    }

    public function update(User $user, Product $product): bool
    {
        return $product->tenant_id === request()->attributes->get('activeTenant')?->id
            && TenantAuthorization::allows(request(), $user, 'products.update');
    }
}
