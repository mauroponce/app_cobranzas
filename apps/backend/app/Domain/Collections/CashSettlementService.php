<?php

namespace App\Domain\Collections;

use App\Domain\Commercial\Decimal;
use App\Domain\Tenancy\Audit;
use App\Domain\Tenancy\AuthorizationService;
use App\Models\CashSettlement;
use App\Models\CashSettlementItem;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CashSettlementService
{
    public function create(Tenant $tenant, User $actor, array $items, ?string $notes = null): CashSettlement
    {
        $membership = $this->require($tenant, $actor, 'settlements.create');

        return DB::transaction(function () use ($tenant, $actor, $items, $notes): CashSettlement {
            $settlement = CashSettlement::create(['tenant_id' => $tenant->id, 'collector_id' => $actor->id, 'presented_by_user_id' => $actor->id, 'declared_amount' => '0.00', 'status' => 'draft', 'notes' => $notes]);
            $total = 0;
            foreach ($items as $item) {
                $payment = Payment::forTenant($tenant)->whereKey($item['payment_id'])->lockForUpdate()->firstOrFail();
                if ($payment->method !== 'cash' || $payment->status !== 'confirmed' || $payment->received_by_user_id !== $actor->id) {
                    throw ValidationException::withMessages(['items' => 'Solo podés rendir efectivo propio confirmado.']);
                }
                $amount = Decimal::toInteger($item['amount']);
                if ($amount <= 0 || $amount > Decimal::toInteger($payment->amount)) {
                    throw ValidationException::withMessages(['items' => 'El importe rendido no es válido.']);
                }
                $already = Decimal::toInteger((string) DB::table('cash_settlement_items')->join('cash_settlements', 'cash_settlements.id', '=', 'cash_settlement_items.settlement_id')->where('cash_settlement_items.tenant_id', $tenant->id)->where('cash_settlement_items.payment_id', $payment->id)->whereNotIn('cash_settlements.status', ['cancelled'])->sum('cash_settlement_items.amount'));
                if ($already + $amount > Decimal::toInteger($payment->amount)) {
                    throw ValidationException::withMessages(['items' => 'El cobro ya está incluido en otra rendición activa.']);
                }
                CashSettlementItem::create(['tenant_id' => $tenant->id, 'settlement_id' => $settlement->id, 'payment_id' => $payment->id, 'amount' => Decimal::format($amount)]);
                $total += $amount;
            }
            $settlement->update(['declared_amount' => Decimal::format($total)]);
            Audit::record('cash_settlement.created', $settlement, $actor, $tenant->id);

            return $settlement->fresh();
        });
    }

    public function submit(Tenant $tenant, User $actor, CashSettlement $settlement): CashSettlement
    {
        $this->require($tenant, $actor, 'settlements.submit');
        abort_unless($settlement->tenant_id === $tenant->id && $settlement->collector_id === $actor->id, 403);

        return DB::transaction(function () use ($tenant, $actor, $settlement): CashSettlement {
            $settlement = CashSettlement::forTenant($tenant)->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            if ($settlement->status !== 'draft') {
                throw ValidationException::withMessages(['settlement' => 'La rendición no está en borrador.']);
            }
            $settlement->update(['status' => 'submitted', 'submitted_at' => now(), 'presented_at' => now()]);
            Audit::record('cash_settlement.submitted', $settlement, $actor, $tenant->id);

            return $settlement->fresh();
        });
    }

    public function approve(Tenant $tenant, User $actor, CashSettlement $settlement, string $receivedAmount, ?string $notes = null): CashSettlement
    {
        $this->require($tenant, $actor, 'settlements.approve');
        abort_unless($settlement->tenant_id === $tenant->id, 404);
        if ($settlement->presented_by_user_id === $actor->id) {
            throw ValidationException::withMessages(['settlement' => 'No podés aprobar tu propia rendición.']);
        }

        return DB::transaction(function () use ($tenant, $actor, $settlement, $receivedAmount, $notes): CashSettlement {
            $settlement = CashSettlement::forTenant($tenant)->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            if (! in_array($settlement->status, ['submitted', 'under_review'], true)) {
                throw ValidationException::withMessages(['settlement' => 'La rendición no está disponible para aprobación.']);
            }
            $received = Decimal::toInteger($receivedAmount);
            if ($received < 0) {
                throw ValidationException::withMessages(['received_amount' => 'El importe recibido no es válido.']);
            }
            $expected = Decimal::toInteger($settlement->declared_amount);
            $status = $received === $expected ? 'approved' : 'disputed';
            $settlement->update(['status' => $status, 'approved_amount' => Decimal::format($received), 'received_amount' => Decimal::format($received), 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'notes' => $notes ?? $settlement->notes]);
            if ($received !== $expected) {
                DB::table('cash_discrepancies')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'settlement_id' => $settlement->id, 'expected_amount' => Decimal::format($expected), 'declared_amount' => Decimal::format($received), 'reason' => $notes, 'created_at' => now(), 'updated_at' => now()]);
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
