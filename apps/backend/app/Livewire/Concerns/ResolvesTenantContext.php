<?php

namespace App\Livewire\Concerns;

use App\Domain\Tenancy\LivewireTenantContext;

trait ResolvesTenantContext
{
    public string $tenantId = '';

    public function mount(): void
    {
        $this->tenantId = request()->attributes->get('activeTenant')?->id ?? $this->tenantId;
        abort_unless($this->tenantId !== '', 403);
    }

    public function booted(): void
    {
        abort_unless(auth()->user() !== null, 403);

        app(LivewireTenantContext::class)->resolve(request(), auth()->user(), $this->tenantId);
    }
}
