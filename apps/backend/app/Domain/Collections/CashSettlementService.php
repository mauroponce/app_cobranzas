<?php

namespace App\Domain\Collections;

use App\Domain\Commercial\Decimal;
use App\Domain\Tenancy\Audit;
use App\Domain\Tenancy\AuthorizationService;
use App\Models\CashSettlement;
use App\Models\CashSettlementItem;
use App\Models\CollectionDeclarationComponent;
use App\Models\CollectionIncident;
use App\Models\CollectionRoute;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CashSettlementService
{
    public function createForRoute(Tenant $tenant, User $actor, CollectionRoute $route, bool $localQueueEmpty, ?string $notes = null): CashSettlement
    {
        $this->require($tenant, $actor, 'settlements.create');
        abort_unless($route->tenant_id === $tenant->id, 404);
        if (! $localQueueEmpty) {
            throw ValidationException::withMessages(['local_queue_empty' => 'Sincronizá todas las operaciones locales antes de presentar la rendición.']);
        }

        return DB::transaction(function () use ($tenant, $actor, $route, $notes): CashSettlement {
            $route = CollectionRoute::forTenant($tenant)->whereKey($route->id)->lockForUpdate()->firstOrFail();
            abort_unless($route->collectorMembership?->user_id === $actor->id, 403);
            if ($route->status !== CollectionRoute::IN_PROGRESS) {
                throw ValidationException::withMessages(['route' => 'La hoja no está en curso.']);
            }
            if ($route->visits()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['route' => 'Todas las visitas deben tener un resultado antes de rendir la hoja.']);
            }
            $existing = CashSettlement::forTenant($tenant)->where('route_id', $route->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $settlement = CashSettlement::create([
                'tenant_id' => $tenant->id, 'route_id' => $route->id, 'collector_id' => $actor->id,
                'presented_by_user_id' => $actor->id, 'declared_amount' => '0.00', 'status' => 'draft', 'notes' => $notes,
            ]);
            $components = CollectionDeclarationComponent::forTenant($tenant)
                ->where('method', 'cash')->where('status', 'pending')
                ->whereHas('declaration.visit', fn ($query) => $query->where('route_id', $route->id))
                ->lockForUpdate()->get();
            $total = 0;
            foreach ($components as $component) {
                $amount = Decimal::toInteger($component->amount);
                CashSettlementItem::create([
                    'tenant_id' => $tenant->id, 'settlement_id' => $settlement->id,
                    'declaration_component_id' => $component->id, 'amount' => Decimal::format($amount),
                ]);
                $total += $amount;
            }
            $settlement->update(['declared_amount' => Decimal::format($total)]);
            Audit::record('cash_settlement.created', $settlement, $actor, $tenant->id, ['route_id' => $route->id]);

            return $settlement->fresh();
        });
    }

    public function submit(Tenant $tenant, User $actor, CashSettlement $settlement): CashSettlement
    {
        $this->require($tenant, $actor, 'settlements.submit');
        abort_unless($settlement->tenant_id === $tenant->id && $settlement->collector_id === $actor->id, 403);

        return DB::transaction(function () use ($tenant, $actor, $settlement): CashSettlement {
            $settlement = CashSettlement::forTenant($tenant)->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            if ($settlement->status === 'submitted') {
                return $settlement;
            }
            if ($settlement->status !== 'draft' || ! $settlement->route_id) {
                throw ValidationException::withMessages(['settlement' => 'La rendición no está disponible para presentar.']);
            }
            $route = CollectionRoute::forTenant($tenant)->whereKey($settlement->route_id)->lockForUpdate()->firstOrFail();
            if ($route->status !== CollectionRoute::IN_PROGRESS) {
                throw ValidationException::withMessages(['route' => 'La hoja ya no está en curso.']);
            }
            $settlement->update(['status' => 'submitted', 'submitted_at' => now(), 'presented_at' => now()]);
            $route->update(['status' => CollectionRoute::SUBMITTED]);
            Audit::record('cash_settlement.submitted', $settlement, $actor, $tenant->id);

            return $settlement->fresh();
        });
    }

    /** @param array<string, string|int> $attributions */
    public function approve(Tenant $tenant, User $actor, CashSettlement $settlement, string $receivedAmount, array $attributions = [], ?string $notes = null): CashSettlement
    {
        $this->require($tenant, $actor, 'settlements.approve');
        abort_unless($settlement->tenant_id === $tenant->id, 404);
        if ($settlement->presented_by_user_id === $actor->id) {
            throw ValidationException::withMessages(['settlement' => 'No podés aprobar tu propia rendición.']);
        }

        return DB::transaction(function () use ($tenant, $actor, $settlement, $receivedAmount, $attributions, $notes): CashSettlement {
            $settlement = CashSettlement::forTenant($tenant)->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            if ($settlement->status !== 'submitted') {
                throw ValidationException::withMessages(['settlement' => 'La rendición no está disponible para aprobación.']);
            }
            $route = CollectionRoute::forTenant($tenant)->whereKey($settlement->route_id)->lockForUpdate()->firstOrFail();
            $items = CashSettlementItem::forTenant($tenant)->where('settlement_id', $settlement->id)->lockForUpdate()->get();
            $received = Decimal::toInteger($receivedAmount);
            $expected = Decimal::toInteger($settlement->declared_amount);
            if ($received < 0 || $received > $expected) {
                throw ValidationException::withMessages(['received_amount' => 'El importe recibido debe estar entre cero y el efectivo declarado.']);
            }
            if ($received !== $expected && $attributions === []) {
                throw ValidationException::withMessages(['attributions' => 'Indicá explícitamente cómo atribuir el efectivo recibido.']);
            }

            $assigned = 0;
            foreach ($items as $item) {
                $declared = Decimal::toInteger($item->amount);
                $itemReceived = $received === $expected ? $declared : Decimal::toInteger((string) ($attributions[$item->declaration_component_id] ?? '0'));
                if ($itemReceived < 0 || $itemReceived > $declared) {
                    throw ValidationException::withMessages(['attributions' => 'Una atribución supera el efectivo declarado.']);
                }
                $assigned += $itemReceived;
            }
            if ($assigned !== $received) {
                throw ValidationException::withMessages(['attributions' => 'La suma atribuida debe coincidir con el efectivo físicamente recibido.']);
            }

            $payments = app(PaymentService::class);
            foreach ($items as $item) {
                $component = CollectionDeclarationComponent::forTenant($tenant)->whereKey($item->declaration_component_id)->lockForUpdate()->firstOrFail();
                $itemReceived = $received === $expected ? Decimal::toInteger($item->amount) : Decimal::toInteger((string) ($attributions[$component->id] ?? '0'));
                $payment = $payments->confirmCashComponent($tenant, $actor, $component, $itemReceived);
                $item->update(['received_amount' => Decimal::format($itemReceived), 'payment_id' => $payment?->id]);
            }

            $status = $received === $expected ? 'approved' : 'disputed';
            $settlement->update([
                'status' => $status, 'approved_amount' => Decimal::format($received), 'received_amount' => Decimal::format($received),
                'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'notes' => $notes ?? $settlement->notes,
            ]);
            $route->update(['status' => CollectionRoute::COMPLETED, 'completed_at' => now()]);
            if ($received !== $expected) {
                DB::table('cash_discrepancies')->insert([
                    'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'settlement_id' => $settlement->id,
                    'expected_amount' => Decimal::format($expected), 'declared_amount' => Decimal::format($expected),
                    'received_amount' => Decimal::format($received), 'reason' => $notes, 'recorded_by_user_id' => $actor->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                CollectionIncident::create([
                    'tenant_id' => $tenant->id, 'route_id' => $route->id, 'type' => 'cash_difference', 'status' => 'open',
                    'details' => 'La rendición declaró '.Decimal::format($expected).' y Administración recibió '.Decimal::format($received).'.',
                    'reported_by' => $actor->id,
                ]);
            }
            Audit::record('cash_settlement.'.$status, $settlement, $actor, $tenant->id, ['expected' => Decimal::format($expected), 'received' => Decimal::format($received)]);

            return $settlement->fresh();
        });
    }

    private function require(Tenant $tenant, User $actor, string $capability): TenantMembership
    {
        $membership = TenantMembership::forTenant($tenant)->where('user_id', $actor->id)->where('status', 'active')->first();
        abort_unless($membership && app(AuthorizationService::class)->allows($membership, $capability), 403);

        return $membership;
    }
}
