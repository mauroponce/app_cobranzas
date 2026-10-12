<?php

namespace App\Domain\Collections;

use App\Domain\Commercial\Decimal;
use App\Domain\Tenancy\Audit;
use App\Domain\Tenancy\AuthorizationService;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerCredit;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentConflict;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PaymentService
{
    public function registerCash(Tenant $tenant, User $actor, array $data, bool $fromSync = false): Payment
    {
        return $this->register($tenant, $actor, $data + ['method' => 'cash'], $fromSync);
    }

    public function registerCashForReview(Tenant $tenant, User $actor, array $data): Payment
    {
        return $this->register($tenant, $actor, $data + ['method' => 'cash'], true, true);
    }

    public function declareTransfer(Tenant $tenant, User $actor, array $data): Payment
    {
        $membership = $this->require($tenant, $actor, 'payments.create');

        return DB::transaction(function () use ($tenant, $actor, $data, $membership): Payment {
            $customer = $this->customer($tenant, $data['customer_id']);
            abort_unless(app(CollectionPortfolioService::class)->canCollect($tenant, $membership, $customer), 403);
            $amount = $this->positive($data['amount']);
            $payment = Payment::create([
                'tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'received_by' => $actor->id,
                'received_by_user_id' => $actor->id, 'registered_by_user_id' => $actor->id,
                'method' => 'bank_transfer', 'origin' => $data['source'] ?? 'dashboard', 'source' => $data['source'] ?? 'dashboard',
                'amount' => Decimal::format($amount), 'unallocated_amount' => Decimal::format($amount), 'currency' => 'ARS',
                'status' => 'pending_verification', 'received_at' => $data['received_at'] ?? now(),
                'external_reference' => $data['reference'] ?? null, 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);
            Audit::record('payment.transfer_declared', $payment, $actor, $tenant->id);

            return $payment;
        });
    }

    public function confirmTransfer(Tenant $tenant, User $actor, Payment $payment): Payment
    {
        $this->require($tenant, $actor, 'payments.verify_transfer');
        abort_unless($payment->tenant_id === $tenant->id && $payment->method === 'bank_transfer', 404);

        return DB::transaction(function () use ($tenant, $actor, $payment): Payment {
            $payment = Payment::forTenant($tenant)->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'pending_verification') {
                throw ValidationException::withMessages(['payment' => 'La transferencia no está pendiente de verificación.']);
            }
            $payment->update(['status' => 'confirmed', 'confirmed_at' => now()]);
            $this->allocate($payment, null);
            Audit::record('payment.transfer_confirmed', $payment, $actor, $tenant->id);

            return $payment->fresh();
        });
    }

    public function reverse(Tenant $tenant, User $actor, Payment $payment, string $reason): Payment
    {
        $this->require($tenant, $actor, 'payments.reverse');
        abort_unless($payment->tenant_id === $tenant->id, 404);

        return DB::transaction(function () use ($tenant, $actor, $payment, $reason): Payment {
            $payment = Payment::forTenant($tenant)->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'confirmed') {
                throw ValidationException::withMessages(['payment' => 'Solo se pueden revertir pagos confirmados.']);
            }
            $approvedSettlement = DB::table('cash_settlement_items')->join('cash_settlements', function ($join) use ($tenant): void {
                $join->on('cash_settlements.id', '=', 'cash_settlement_items.settlement_id')->where('cash_settlements.tenant_id', $tenant->id);
            })->where('cash_settlement_items.tenant_id', $tenant->id)->where('cash_settlement_items.payment_id', $payment->id)->where('cash_settlements.status', 'approved')->exists();
            if ($approvedSettlement) {
                throw ValidationException::withMessages(['payment' => 'El pago ya fue rendido y requiere un ajuste de caja auditado.']);
            }
            $allocations = PaymentAllocation::forTenant($tenant)->where('payment_id', $payment->id)->lockForUpdate()->get();
            foreach ($allocations as $allocation) {
                $entry = CustomerAccountEntry::forTenant($tenant)->whereKey($allocation->account_entry_id)->lockForUpdate()->firstOrFail();
                $amount = Decimal::toInteger($allocation->amount);
                $entry->update(['outstanding_amount' => Decimal::format(Decimal::toInteger($entry->outstanding_amount) + $amount), 'status' => 'open']);
                if ($entry->installment_id) {
                    $installment = Installment::forTenant($tenant)->whereKey($entry->installment_id)->lockForUpdate()->first();
                    $installment?->update(['outstanding_amount' => Decimal::format(Decimal::toInteger($installment->outstanding_amount) + $amount), 'status' => 'pending']);
                }
            }
            CustomerCredit::forTenant($tenant)->where('payment_id', $payment->id)->update(['remaining_amount' => '0.00', 'status' => 'reversed']);
            DB::table('payment_reversals')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'payment_id' => $payment->id, 'reversed_by' => $actor->id, 'reason' => $reason, 'created_at' => now()]);
            $payment->update(['status' => 'reversed', 'reversed_at' => now(), 'unallocated_amount' => '0.00']);
            Audit::record('payment.reversed', $payment, $actor, $tenant->id, ['reason' => $reason]);

            return $payment->fresh();
        });
    }

    private function register(Tenant $tenant, User $actor, array $data, bool $fromSync, bool $forceReview = false): Payment
    {
        $membership = TenantMembership::forTenant($tenant)->where('user_id', $actor->id)->where('status', 'active')->first();
        $decision = $membership ? app(AuthorizationService::class)->decision($membership, 'payments.create') : ['allowed' => false, 'scope' => null];
        $authorized = ! $forceReview && $decision['allowed'];
        if (! $authorized && ! $fromSync) {
            abort(403);
        }

        return DB::transaction(function () use ($tenant, $actor, $data, $fromSync, $authorized, $membership): Payment {
            $customer = $this->customer($tenant, $data['customer_id']);
            if ($authorized && ! app(CollectionPortfolioService::class)->canCollect($tenant, $membership, $customer)) {
                abort(403);
            }
            $amount = $this->positive($data['amount']);
            $operationId = $data['client_operation_id'] ?? null;
            if ($operationId && ($existing = Payment::forTenant($tenant)->where('client_operation_id', $operationId)->lockForUpdate()->first())) {
                return $existing;
            }
            try {
                $payment = DB::transaction(fn (): Payment => Payment::create([
                    'tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'received_by' => $actor->id,
                    'received_by_user_id' => $actor->id, 'registered_by_user_id' => $actor->id,
                    'method' => 'cash', 'origin' => $data['source'] ?? ($fromSync ? 'mobile' : 'dashboard'), 'source' => $data['source'] ?? ($fromSync ? 'mobile' : 'dashboard'),
                    'amount' => Decimal::format($amount), 'unallocated_amount' => Decimal::format($amount), 'currency' => 'ARS',
                    'status' => $authorized ? 'confirmed' : 'under_review', 'received_at' => $data['received_at'] ?? now(), 'confirmed_at' => $authorized ? now() : null,
                    'mobile_operation_id' => $operationId, 'client_operation_id' => $operationId, 'notes' => $data['notes'] ?? null,
                ]));
            } catch (QueryException $exception) {
                if (! $operationId || ! ($existing = Payment::forTenant($tenant)->where('client_operation_id', $operationId)->lockForUpdate()->first())) {
                    throw $exception;
                }

                return $existing;
            }
            if (! $authorized) {
                PaymentConflict::create(['tenant_id' => $tenant->id, 'payment_id' => $payment->id, 'type' => 'authorization_revoked', 'details' => ['recorded_at' => $data['received_at'] ?? null]]);
                Audit::record('payment.under_review', $payment, $actor, $tenant->id);

                return $payment;
            }
            try {
                $this->allocate($payment, $data['allocations'] ?? null);
            } catch (ValidationException $exception) {
                if (! $fromSync) {
                    throw $exception;
                }
                $payment->update(['status' => 'under_review']);
                PaymentConflict::create(['tenant_id' => $tenant->id, 'payment_id' => $payment->id, 'type' => 'stale_allocation', 'details' => ['errors' => $exception->errors()]]);
            }
            Audit::record('payment.cash_registered', $payment, $actor, $tenant->id);

            return $payment->fresh();
        });
    }

    private function allocate(Payment $payment, ?array $requested): void
    {
        $tenantId = $payment->tenant_id;
        $available = Decimal::toInteger($payment->amount);
        $targets = $requested === null ? CustomerAccountEntry::forTenant($tenantId)->where('customer_id', $payment->customer_id)->where('status', 'open')->where('outstanding_amount', '>', 0)->orderByRaw('CASE WHEN due_date <= CURRENT_DATE THEN 0 ELSE 1 END')->orderBy('due_date')->get()->map(fn ($entry) => ['account_entry_id' => $entry->id])->all() : $requested;
        foreach ($targets as $target) {
            if ($available === 0) {
                break;
            }
            $entry = CustomerAccountEntry::forTenant($tenantId)->whereKey($target['account_entry_id'] ?? null)->where('customer_id', $payment->customer_id)->lockForUpdate()->first();
            if (! $entry || $entry->status !== 'open') {
                throw ValidationException::withMessages(['allocations' => 'La obligación indicada no está disponible.']);
            }
            $outstanding = Decimal::toInteger($entry->outstanding_amount);
            $requestedAmount = array_key_exists('amount', $target) ? $this->positive($target['amount']) : min($available, $outstanding);
            if ($requestedAmount > $available || $requestedAmount > $outstanding) {
                throw ValidationException::withMessages(['allocations' => 'La imputación supera el saldo disponible.']);
            }
            PaymentAllocation::create(['tenant_id' => $tenantId, 'payment_id' => $payment->id, 'customer_id' => $payment->customer_id, 'account_entry_id' => $entry->id, 'installment_id' => $entry->installment_id, 'amount' => Decimal::format($requestedAmount)]);
            $newOutstanding = $outstanding - $requestedAmount;
            $entry->update(['outstanding_amount' => Decimal::format($newOutstanding), 'status' => $newOutstanding === 0 ? 'settled' : 'open']);
            if ($entry->installment_id) {
                $installment = Installment::forTenant($tenantId)->whereKey($entry->installment_id)->lockForUpdate()->firstOrFail();
                $installment->update(['outstanding_amount' => Decimal::format($newOutstanding), 'status' => $newOutstanding === 0 ? 'paid' : 'pending']);
            }
            $available -= $requestedAmount;
        }
        $payment->update(['unallocated_amount' => Decimal::format($available)]);
        if ($available > 0) {
            CustomerCredit::create(['tenant_id' => $tenantId, 'customer_id' => $payment->customer_id, 'payment_id' => $payment->id, 'amount' => Decimal::format($available), 'remaining_amount' => Decimal::format($available), 'status' => 'open']);
        }
    }

    private function require(Tenant $tenant, User $actor, string $capability): TenantMembership
    {
        $membership = TenantMembership::forTenant($tenant)->where('user_id', $actor->id)->where('status', 'active')->first();
        abort_unless($membership && app(AuthorizationService::class)->allows($membership, $capability), 403);

        return $membership;
    }

    private function customer(Tenant $tenant, string $id): Customer
    {
        return Customer::forTenant($tenant)->whereKey($id)->firstOrFail();
    }

    private function positive(string|int $amount): int
    {
        $cents = Decimal::toInteger($amount);
        if ($cents <= 0) {
            throw ValidationException::withMessages(['amount' => 'El importe debe ser mayor que cero.']);
        }

        return $cents;
    }
}
