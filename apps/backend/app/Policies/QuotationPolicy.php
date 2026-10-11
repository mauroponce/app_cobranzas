<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantAuthorization;
use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    public function view(User $user, Quotation $quotation): bool
    {
        return $quotation->tenant_id === request()->attributes->get('activeTenant')?->id
            && TenantAuthorization::allows(request(), $user, 'quotations.view')
            && (TenantAuthorization::allows(request(), $user, 'quotations.view_all') || $quotation->seller_id === $user->id);
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $this->view($user, $quotation) && $quotation->status === 'draft'
            && TenantAuthorization::allows(request(), $user, 'quotations.update');
    }
}
