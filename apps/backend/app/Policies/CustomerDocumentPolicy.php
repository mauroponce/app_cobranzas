<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantAuthorization;
use App\Models\CustomerDocument;
use App\Models\User;

class CustomerDocumentPolicy
{
    public function view(User $user, CustomerDocument $document): bool
    {
        return $document->tenant_id === request()->attributes->get('activeTenant')?->id
            && TenantAuthorization::allows(request(), $user, 'sales.view')
            && (TenantAuthorization::allows(request(), $user, 'sales.approve') || $document->uploaded_by === $user->id || $document->application?->seller_id === $user->id);
    }

    public function verify(User $user, CustomerDocument $document): bool
    {
        return $document->tenant_id === request()->attributes->get('activeTenant')?->id
            && TenantAuthorization::allows(request(), $user, 'sales.documents.verify')
            && $document->uploaded_by !== $user->id && $document->verification_status === 'pending';
    }
}
