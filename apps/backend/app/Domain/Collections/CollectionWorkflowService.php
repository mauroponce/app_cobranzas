<?php

namespace App\Domain\Collections;

use App\Domain\Commercial\Decimal;
use App\Domain\Tenancy\Audit;
use App\Models\CollectionDeclaration;
use App\Models\CollectionDeclarationComponent;
use App\Models\CollectionIncident;
use App\Models\CollectionRoute;
use App\Models\CollectionVisit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CollectionWorkflowService
{
    public function startRoute(Tenant $tenant, User $actor, CollectionRoute $route): CollectionRoute
    {
        return DB::transaction(function () use ($tenant, $actor, $route): CollectionRoute {
            $route = CollectionRoute::forTenant($tenant)->whereKey($route->id)->lockForUpdate()->firstOrFail();
            abort_unless($route->collectorMembership?->user_id === $actor->id, 403);
            if ($route->status !== 'published') throw ValidationException::withMessages(['route' => 'La hoja no está disponible para iniciar.']);
            $route->update(['status' => 'in_progress', 'started_at' => now()]);
            Audit::record('collection_route.started', $route, $actor, $tenant->id);
            return $route->fresh();
        });
    }

    public function declare(Tenant $tenant, User $actor, array $data): CollectionDeclaration
    {
        return DB::transaction(function () use ($tenant, $actor, $data): CollectionDeclaration {
            $visit = CollectionVisit::forTenant($tenant)->whereKey($data['visit_id'])->lockForUpdate()->firstOrFail();
            $route = CollectionRoute::forTenant($tenant)->whereKey($visit->route_id)->lockForUpdate()->firstOrFail();
            abort_unless($route->collectorMembership?->user_id === $actor->id, 403);
            if ($visit->status !== 'pending') throw ValidationException::withMessages(['visit' => 'La visita ya no admite declaraciones.']);
            if ($route->status !== 'in_progress') throw ValidationException::withMessages(['route' => 'La hoja debe estar iniciada para registrar una declaración.']);
            $total = array_sum(array_map(fn ($component) => Decimal::toInteger($component['amount']), $data['components']));
            if ($total <= 0) throw ValidationException::withMessages(['components' => 'El importe debe ser mayor que cero.']);
            $declaration = CollectionDeclaration::firstOrCreate(['tenant_id' => $tenant->id, 'operation_id' => $data['operation_id']], [
                'visit_id' => $visit->id, 'customer_id' => $visit->customer_id, 'declared_by_user_id' => $actor->id,
                'amount' => Decimal::format($total), 'status' => 'pending', 'declared_at' => $data['declared_at'] ?? now(), 'received_at' => now(),
            ]);
            if ($declaration->wasRecentlyCreated) {
                foreach ($data['components'] as $component) CollectionDeclarationComponent::create(['tenant_id' => $tenant->id, 'declaration_id' => $declaration->id, 'method' => $component['method'], 'amount' => Decimal::format(Decimal::toInteger($component['amount'])), 'reference' => $component['reference'] ?? null]);
                $visit->update(['status' => 'recorded', 'outcome' => 'paid', 'recorded_at' => $data['declared_at'] ?? now(), 'received_at' => now()]);
                Audit::record('collection_declaration.created', $declaration, $actor, $tenant->id);
                DB::afterCommit(
                    fn () => app(CollectionDashboardNotifier::class)->notify($tenant->id),
                );
            }
            return $declaration;
        });
    }

    public function noPayment(Tenant $tenant, User $actor, CollectionVisit $visit, string $reason, ?string $notes): CollectionVisit
    {
        return DB::transaction(function () use ($tenant, $actor, $visit, $reason, $notes): CollectionVisit {
            $visit = CollectionVisit::forTenant($tenant)->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $route = CollectionRoute::forTenant($tenant)->whereKey($visit->route_id)->lockForUpdate()->firstOrFail();
            abort_unless($route->collectorMembership?->user_id === $actor->id, 403);
            if ($visit->status !== 'pending') throw ValidationException::withMessages(['visit' => 'La visita ya fue registrada.']);
            if ($route->status !== 'in_progress') throw ValidationException::withMessages(['route' => 'La hoja debe estar iniciada para registrar la visita.']);
            if ($reason === 'other' && blank($notes)) throw ValidationException::withMessages(['notes' => 'Detallá el motivo.']);
            $visit->update(['status' => 'recorded', 'outcome' => 'no_payment', 'no_payment_reason' => $reason, 'no_payment_notes' => $notes, 'recorded_at' => now(), 'received_at' => now()]);
            Audit::record('collection_visit.no_payment', $visit, $actor, $tenant->id);
            return $visit->fresh();
        });
    }
}
