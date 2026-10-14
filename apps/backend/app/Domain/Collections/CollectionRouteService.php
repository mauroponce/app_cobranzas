<?php

namespace App\Domain\Collections;

use App\Domain\Commercial\Decimal;
use App\Domain\Tenancy\Audit;
use App\Domain\Tenancy\AuthorizationService;
use App\Models\CollectionRoute;
use App\Models\CollectionVisit;
use App\Models\CustomerAccountEntry;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CollectionRouteService
{
    /** @param list<string> $saleIds */
    public function program(Tenant $tenant, User $actor, string $collectorMembershipId, string $routeDate, array $saleIds): CollectionRoute
    {
        $membership = $this->require($tenant, $actor, 'routes.manage');

        return DB::transaction(function () use ($tenant, $actor, $membership, $collectorMembershipId, $routeDate, $saleIds): CollectionRoute {
            $collector = TenantMembership::forTenant($tenant)->whereKey($collectorMembershipId)->where('role', 'collector')->where('status', 'active')->with('user')->lockForUpdate()->firstOrFail();
            $sales = Sale::forTenant($tenant)->whereIn('id', array_values(array_unique($saleIds)))->where('status', 'confirmed')->with('customer')->lockForUpdate()->get();
            if ($sales->count() !== count(array_unique($saleIds))) {
                throw ValidationException::withMessages(['sale_ids' => 'Cada visita debe corresponder a una venta confirmada de la empresa.']);
            }

            $date = Carbon::parse($routeDate, $tenant->timezone)->toDateString();
            $route = CollectionRoute::create([
                'tenant_id' => $tenant->id,
                'route_date' => $date,
                'collector_membership_id' => $collector->id,
                'name' => 'Hoja '.$date.' — '.$collector->user->name,
                'status' => 'published',
                'created_by_membership_id' => $membership->id,
                'published_by_membership_id' => $membership->id,
                'published_at' => now(),
                'version' => 1,
            ]);

            foreach ($sales->values() as $index => $sale) {
                $entries = CustomerAccountEntry::forTenant($tenant)->where('sale_id', $sale->id)->where('status', 'open')->where('outstanding_amount', '>', 0)->orderBy('due_date')->lockForUpdate()->get();
                $scheduledAmount = $entries->sum(fn (CustomerAccountEntry $entry) => Decimal::toInteger($entry->outstanding_amount));
                $snapshot = $entries->map(fn (CustomerAccountEntry $entry): array => [
                    'account_entry_id' => $entry->id,
                    'installment_id' => $entry->installment_id,
                    'outstanding_amount' => $entry->outstanding_amount,
                    'due_date' => $entry->due_date?->toDateString(),
                ])->all();
                CollectionVisit::create([
                    'tenant_id' => $tenant->id,
                    'route_id' => $route->id,
                    'sale_id' => $sale->id,
                    'customer_id' => $sale->customer_id,
                    'sequence' => $index + 1,
                    'status' => 'pending',
                    'scheduled_amount' => Decimal::format($scheduledAmount),
                    'obligation_snapshot' => $snapshot,
                ]);
            }

            Audit::record('collection_route.programmed', $route, $actor, $tenant->id, ['sales' => $sales->pluck('id')->all()]);

            return $route->fresh('visits');
        });
    }

    private function require(Tenant $tenant, User $actor, string $capability): TenantMembership
    {
        $membership = TenantMembership::forTenant($tenant)->where('user_id', $actor->id)->where('status', 'active')->first();
        abort_unless($membership && app(AuthorizationService::class)->allows($membership, $capability), 403);

        return $membership;
    }
}
