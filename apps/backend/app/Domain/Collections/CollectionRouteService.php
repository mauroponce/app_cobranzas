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
use Illuminate\Support\Str;
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

    public function reassignVisit(Tenant $tenant, User $actor, CollectionVisit $visit, string $collectorMembershipId, string $reason): CollectionVisit
    {
        $membership = $this->require($tenant, $actor, 'collections.reassign');

        return DB::transaction(function () use ($tenant, $actor, $membership, $visit, $collectorMembershipId, $reason): CollectionVisit {
            $visit = CollectionVisit::forTenant($tenant)->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            if ($visit->status !== 'pending') {
                throw ValidationException::withMessages(['visit' => 'Solo se pueden reasignar visitas pendientes.']);
            }
            $sourceRoute = CollectionRoute::forTenant($tenant)->whereKey($visit->route_id)->lockForUpdate()->firstOrFail();
            $collector = TenantMembership::forTenant($tenant)->whereKey($collectorMembershipId)->where('role', 'collector')->where('status', 'active')->with('user')->lockForUpdate()->firstOrFail();
            if ($collector->id === $sourceRoute->collector_membership_id) {
                throw ValidationException::withMessages(['collector_membership_id' => 'La visita ya pertenece a ese cobrador.']);
            }
            $targetRoute = CollectionRoute::forTenant($tenant)
                ->where('collector_membership_id', $collector->id)->whereDate('route_date', $sourceRoute->route_date)
                ->whereIn('status', ['published', 'in_progress'])->lockForUpdate()->first();
            if (! $targetRoute) {
                $targetRoute = CollectionRoute::create([
                    'tenant_id' => $tenant->id, 'route_date' => $sourceRoute->route_date, 'collector_membership_id' => $collector->id,
                    'name' => 'Hoja '.$sourceRoute->route_date->toDateString().' — '.$collector->user->name,
                    'status' => 'published', 'created_by_membership_id' => $membership->id,
                    'published_by_membership_id' => $membership->id, 'published_at' => now(), 'version' => 1,
                ]);
            }
            $sequence = ((int) $targetRoute->visits()->max('sequence')) + 1;
            $targetVisit = CollectionVisit::create([
                'tenant_id' => $tenant->id, 'route_id' => $targetRoute->id, 'sale_id' => $visit->sale_id,
                'customer_id' => $visit->customer_id, 'sequence' => $sequence, 'status' => 'pending',
                'scheduled_amount' => $visit->scheduled_amount, 'obligation_snapshot' => $visit->obligation_snapshot,
            ]);
            $visit->update(['status' => 'reassigned', 'version' => $visit->version + 1]);
            $sourceRoute->increment('version');
            $targetRoute->increment('version');
            $tenant->increment('authorization_version');
            DB::table('collection_visit_reassignments')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'source_visit_id' => $visit->id,
                'target_visit_id' => $targetVisit->id, 'from_route_id' => $sourceRoute->id, 'to_route_id' => $targetRoute->id,
                'from_collector_membership_id' => $sourceRoute->collector_membership_id,
                'to_collector_membership_id' => $collector->id, 'reason' => $reason,
                'reassigned_by_user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            Audit::record('collection_visit.reassigned', $targetVisit, $actor, $tenant->id, ['source_visit_id' => $visit->id, 'reason' => $reason]);

            return $targetVisit;
        });
    }

    private function require(Tenant $tenant, User $actor, string $capability): TenantMembership
    {
        $membership = TenantMembership::forTenant($tenant)->where('user_id', $actor->id)->where('status', 'active')->first();
        abort_unless($membership && app(AuthorizationService::class)->allows($membership, $capability), 403);

        return $membership;
    }
}
