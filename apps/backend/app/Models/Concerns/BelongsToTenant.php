<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $query, Tenant|string $tenant): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('tenant_id'), $tenant instanceof Tenant ? $tenant->id : $tenant);
    }
}
