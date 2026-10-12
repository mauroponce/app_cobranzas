<?php

namespace App\Domain\Collections;

use App\Domain\Tenancy\Audit;
use App\Domain\Tenancy\AuthorizationService;
use App\Models\Customer;
use App\Models\CustomerCollectorAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CollectionAssignmentService
{
    public function __construct(private AuthorizationService $authorization, private CollectionPortfolioService $portfolio) {}

    public function assign(Tenant $tenant, User $actor, Customer $customer, TenantMembership $collector, ?string $reason = null): CustomerCollectorAssignment
    {
        return DB::transaction(function () use ($tenant, $actor, $customer, $collector, $reason): CustomerCollectorAssignment {
            $actorMembership = $this->actorMembership($tenant, $actor, 'customers.assign_collector');
            $customer = Customer::forTenant($tenant)->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $collector = TenantMembership::forTenant($tenant)->whereKey($collector->id)->lockForUpdate()->firstOrFail();
            if (! $this->portfolio->eligibleCollector($tenant, $collector)) {
                throw ValidationException::withMessages(['collector_membership_id' => 'El usuario seleccionado no puede registrar cobros.']);
            }
            CustomerCollectorAssignment::forTenant($tenant)->where('customer_id', $customer->id)->whereNull('unassigned_at')->lockForUpdate()->update(['unassigned_at' => now(), 'reason' => $reason]);
            $assignment = CustomerCollectorAssignment::create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'collector_membership_id' => $collector->id, 'assigned_by_membership_id' => $actorMembership->id, 'assigned_at' => now(), 'reason' => $reason]);
            $this->touchPortfolioVersion($tenant);
            Audit::record('collection_portfolio.assigned', $assignment, $actor, $tenant->id, ['collector_membership_id' => $collector->id]);

            return $assignment;
        });
    }

    public function unassign(Tenant $tenant, User $actor, Customer $customer, ?string $reason = null): void
    {
        DB::transaction(function () use ($tenant, $actor, $customer, $reason): void {
            $this->actorMembership($tenant, $actor, 'customers.unassign_collector');
            $customer = Customer::forTenant($tenant)->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $assignment = CustomerCollectorAssignment::forTenant($tenant)->where('customer_id', $customer->id)->whereNull('unassigned_at')->lockForUpdate()->firstOrFail();
            $assignment->update(['unassigned_at' => now(), 'reason' => $reason]);
            $this->touchPortfolioVersion($tenant);
            Audit::record('collection_portfolio.unassigned', $assignment, $actor, $tenant->id);
        });
    }

    private function actorMembership(Tenant $tenant, User $actor, string $capability): TenantMembership
    {
        $membership = TenantMembership::forTenant($tenant)->where('user_id', $actor->id)->where('status', 'active')->first();
        abort_unless($membership && $this->authorization->allows($membership, $capability), 403);

        return $membership;
    }

    private function touchPortfolioVersion(Tenant $tenant): void
    {
        Tenant::whereKey($tenant->id)->increment('authorization_version');
    }
}
