<?php

namespace App\Domain\Sales;

use App\Domain\Tenancy\Audit;
use App\Models\LateFeePolicy;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LateFeePolicyService
{
    public function create(Tenant $tenant, User $actor, array $data): LateFeePolicy
    {
        app(SalesAuthorization::class)->require($tenant->id, $actor, 'sales.policies.manage');

        return DB::transaction(function () use ($tenant, $actor, $data): LateFeePolicy {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $overlaps = LateFeePolicy::forTenant($tenant)->whereDate('effective_from', '<=', $data['effective_until'] ?? '9999-12-31')
                ->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $data['effective_from']))->exists();
            if ($overlaps || ($data['effective_until'] ?? null) && $data['effective_until'] < $data['effective_from']) {
                throw ValidationException::withMessages(['effective_from' => 'La vigencia de la política se superpone o es inválida.']);
            }
            $version = (int) LateFeePolicy::forTenant($tenant)->max('version') + 1;
            $policy = LateFeePolicy::create(array_merge($data, ['tenant_id' => $tenant->id, 'version' => $version, 'created_by' => $actor->id]));
            Audit::record('late_fee_policy.created', $policy, $actor, $tenant->id);

            return $policy;
        });
    }
}
