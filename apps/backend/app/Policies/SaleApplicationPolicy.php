<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantAuthorization;
use App\Models\SaleApplication;
use App\Models\User;

class SaleApplicationPolicy
{
    public function view(User $user, SaleApplication $application): bool
    {
        return $application->tenant_id === request()->attributes->get('activeTenant')?->id
            && TenantAuthorization::allows(request(), $user, 'sales.view')
            && (TenantAuthorization::allows(request(), $user, 'sales.view_all') || $application->seller_id === $user->id);
    }
}
