<?php

namespace App\Domain\Collections;

use App\Domain\Commercial\Decimal;
use App\Domain\Tenancy\Audit;
use App\Domain\Tenancy\AuthorizationService;
use App\Models\CollectionDeclaration;
use App\Models\CollectionDeclarationComponent;
use App\Models\CollectionRoute;
use App\Models\CollectionVisit;
use App\Models\Tenant;
use App\Models\TenantMembership;
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
            if ($route->status !== 'published') {
                throw ValidationException::withMessages(['route' => 'La hoja no está disponible para iniciar.']);
            }
            $route->update(['status' => 'in_progress', 'started_at' => now()]);
            Audit::record('collection_route.started', $route, $actor, $tenant->id);

            return $route->fresh();
        });
    }

    public function declare(Tenant $tenant, User $actor, array $data): CollectionDeclaration
    {
        return DB::transaction(function () use ($tenant, $actor, $data): CollectionDeclaration {
            $membership = TenantMembership::forTenant($tenant)->where('user_id', $actor->id)->where('status', 'active')->first();
            abort_unless($membership && app(AuthorizationService::class)->allows($membership, 'collections.register'), 403);
            $visit = CollectionVisit::forTenant($tenant)->whereKey($data['visit_id'])->lockForUpdate()->firstOrFail();
            $route = CollectionRoute::forTenant($tenant)->whereKey($visit->route_id)->lockForUpdate()->firstOrFail();
            abort_unless($route->collectorMembership?->user_id === $actor->id, 403);
            $acceptsDeclaration = $visit->status === 'pending'
                || ($visit->status === 'recorded' && $visit->outcome === 'paid');
            if (! $acceptsDeclaration) {
                throw ValidationException::withMessages(['visit' => 'La visita está cerrada y no admite nuevos cobros.']);
            }
            if ($route->status !== 'in_progress') {
                throw ValidationException::withMessages(['route' => 'La hoja debe estar iniciada para registrar una declaración.']);
            }
            $amounts = array_map(fn ($component) => Decimal::toInteger($component['amount']), $data['components']);
            if (collect($amounts)->contains(fn (int $amount) => $amount <= 0)) {
                throw ValidationException::withMessages(['components' => 'Cada importe debe ser mayor que cero.']);
            }
            $total = array_sum($amounts);
            $payloadHash = hash('sha256', json_encode($this->canonical(['visit_id' => $visit->id, 'components' => $data['components']]), JSON_THROW_ON_ERROR));
            $existing = CollectionDeclaration::forTenant($tenant)->where('operation_id', $data['operation_id'])->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                    throw ValidationException::withMessages(['operation_id' => 'El UUID fue reutilizado con otro contenido.']);
                }

                return $existing;
            }
            $declaration = CollectionDeclaration::create([
                'tenant_id' => $tenant->id, 'operation_id' => $data['operation_id'],
                'visit_id' => $visit->id, 'customer_id' => $visit->customer_id, 'declared_by_user_id' => $actor->id,
                'payload_hash' => $payloadHash, 'amount' => Decimal::format($total), 'status' => 'pending', 'declared_at' => $data['declared_at'] ?? now(), 'received_at' => now(),
            ]);
            foreach ($data['components'] as $component) {
                CollectionDeclarationComponent::create(['tenant_id' => $tenant->id, 'declaration_id' => $declaration->id, 'method' => $component['method'], 'amount' => Decimal::format(Decimal::toInteger($component['amount'])), 'status' => 'pending', 'reference' => $component['reference'] ?? null]);
            }
            if ($visit->status === 'pending') {
                $visit->update(['status' => 'recorded', 'outcome' => 'paid', 'recorded_at' => $data['declared_at'] ?? now(), 'received_at' => now()]);
            }
            Audit::record('collection_declaration.created', $declaration, $actor, $tenant->id);
            DB::afterCommit(fn () => app(CollectionDashboardNotifier::class)->notify($tenant->id));

            return $declaration;
        });
    }

    public function noPayment(Tenant $tenant, User $actor, CollectionVisit $visit, string $reason, ?string $notes): CollectionVisit
    {
        return DB::transaction(function () use ($tenant, $actor, $visit, $reason, $notes): CollectionVisit {
            $visit = CollectionVisit::forTenant($tenant)->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $route = CollectionRoute::forTenant($tenant)->whereKey($visit->route_id)->lockForUpdate()->firstOrFail();
            abort_unless($route->collectorMembership?->user_id === $actor->id, 403);
            if ($visit->status !== 'pending') {
                throw ValidationException::withMessages(['visit' => 'La visita ya fue registrada.']);
            }
            if ($route->status !== 'in_progress') {
                throw ValidationException::withMessages(['route' => 'La hoja debe estar iniciada para registrar la visita.']);
            }
            if ($reason === 'other' && blank($notes)) {
                throw ValidationException::withMessages(['notes' => 'Detallá el motivo.']);
            }
            $visit->update(['status' => 'recorded', 'outcome' => 'no_payment', 'no_payment_reason' => $reason, 'no_payment_notes' => $notes, 'recorded_at' => now(), 'received_at' => now()]);
            Audit::record('collection_visit.no_payment', $visit, $actor, $tenant->id);

            return $visit->fresh();
        });
    }

    private function canonical(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonical($item);
            }
        }

        return $value;
    }
}
