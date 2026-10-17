<?php

namespace App\Domain\Collections;

use App\Domain\Commercial\Decimal;
use App\Domain\Tenancy\Audit;
use App\Domain\Tenancy\AuthorizationService;
use App\Models\CollectionDeclaration;
use App\Models\CollectionDeclarationComponent;
use App\Models\CollectionIncident;
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
    public function approveDeclaration(Tenant $tenant, User $actor, CollectionDeclaration $declaration, ?string $cashReceived = null): CollectionDeclaration
    {
        $this->require($tenant, $actor, 'payments.verify');

        if ($cashReceived !== null) {
            throw ValidationException::withMessages(['cash_received' => 'El efectivo del cobrador solo puede confirmarse desde la rendición de su hoja.']);
        }

        return DB::transaction(function () use ($tenant, $actor, $declaration): CollectionDeclaration {
            $declaration = CollectionDeclaration::forTenant($tenant)->whereKey($declaration->id)->lockForUpdate()->firstOrFail();
            $components = CollectionDeclarationComponent::forTenant($tenant)->where('declaration_id', $declaration->id)->lockForUpdate()->get();
            if ($components->contains('method', 'cash')) {
                throw ValidationException::withMessages(['declaration' => 'La declaración contiene efectivo y debe resolverse desde la rendición de su hoja.']);
            }
            if ($components->isEmpty() || $components->every(fn (CollectionDeclarationComponent $component) => $component->status !== 'pending')) {
                throw ValidationException::withMessages(['declaration' => 'La declaración no tiene transferencias pendientes.']);
            }

            foreach ($components as $component) {
                $this->confirmDeclarationComponent($tenant, $actor, $component);
            }
            $declaration->update(['status' => 'approved', 'approved_by_user_id' => $actor->id, 'approved_at' => now()]);
            Audit::record('collection_declaration.approved', $declaration, $actor, $tenant->id);

            return $declaration->fresh();
        });
    }

    public function confirmTransferComponent(Tenant $tenant, User $actor, CollectionDeclarationComponent $component): CollectionDeclarationComponent
    {
        $this->require($tenant, $actor, 'payments.verify_transfer');
        abort_unless($component->tenant_id === $tenant->id && $component->method === 'bank_transfer', 404);

        return DB::transaction(function () use ($tenant, $actor, $component): CollectionDeclarationComponent {
            $component = CollectionDeclarationComponent::forTenant($tenant)->whereKey($component->id)->lockForUpdate()->firstOrFail();
            $this->confirmDeclarationComponent($tenant, $actor, $component);
            $this->refreshDeclarationStatus($tenant, $actor, $component->declaration_id);

            return $component->fresh();
        });
    }

    public function confirmCashComponent(Tenant $tenant, User $actor, CollectionDeclarationComponent $component, int $receivedCents): ?Payment
    {
        $this->require($tenant, $actor, 'settlements.approve');
        if ($component->method !== 'cash' || $component->status !== 'pending') {
            throw ValidationException::withMessages(['items' => 'El componente de efectivo ya fue conciliado o no es válido.']);
        }
        if ($receivedCents < 0 || $receivedCents > Decimal::toInteger($component->amount)) {
            throw ValidationException::withMessages(['items' => 'El efectivo atribuido no es válido.']);
        }

        $payment = $receivedCents > 0 ? $this->createDeclarationPayment($tenant, $actor, $component, $receivedCents) : null;
        $component->update([
            'status' => $receivedCents > 0 ? 'confirmed' : 'rejected',
            'confirmed_amount' => Decimal::format($receivedCents),
            'payment_id' => $payment?->id,
            'reconciled_by_user_id' => $actor->id,
            'reconciled_at' => now(),
        ]);
        $this->refreshDeclarationStatus($tenant, $actor, $component->declaration_id);

        return $payment;
    }

    public function registerCash(Tenant $tenant, User $actor, array $data, bool $fromSync = false): Payment
    {
        if ($fromSync) {
            throw ValidationException::withMessages(['payment' => 'El flujo móvil heredado de efectivo está deshabilitado.']);
        }

        return $this->register($tenant, $actor, $data + ['method' => 'cash'], $fromSync);
    }

    public function registerCashForReview(Tenant $tenant, User $actor, array $data): Payment
    {
        return $this->register($tenant, $actor, $data + ['method' => 'cash'], true, true);
    }

    public function declareTransfer(Tenant $tenant, User $actor, array $data): Payment
    {
        $membership = $this->require($tenant, $actor, 'payments.create');
        abort_if($membership->role === 'collector', 403, 'La transferencia del cobrador debe registrarse como declaración de su visita.');

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
        if (! $fromSync && $membership?->role === 'collector') {
            abort(403, 'El efectivo del cobrador debe registrarse como declaración y rendirse con su hoja.');
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

    private function confirmDeclarationComponent(Tenant $tenant, User $actor, CollectionDeclarationComponent $component): void
    {
        if ($component->method !== 'bank_transfer' || $component->status !== 'pending') {
            throw ValidationException::withMessages(['component' => 'La transferencia no está pendiente de verificación.']);
        }
        $amount = Decimal::toInteger($component->amount);
        $payment = $this->createDeclarationPayment($tenant, $actor, $component, $amount);
        $component->update(['status' => 'confirmed', 'confirmed_amount' => Decimal::format($amount), 'payment_id' => $payment->id, 'reconciled_by_user_id' => $actor->id, 'reconciled_at' => now()]);
        Audit::record('collection_declaration.transfer_confirmed', $component, $actor, $tenant->id);
    }

    private function createDeclarationPayment(Tenant $tenant, User $actor, CollectionDeclarationComponent $component, int $amount): Payment
    {
        $declaration = CollectionDeclaration::forTenant($tenant)->with('visit')->whereKey($component->declaration_id)->lockForUpdate()->firstOrFail();
        $payment = Payment::create([
            'tenant_id' => $tenant->id, 'customer_id' => $declaration->customer_id, 'received_by' => $declaration->declared_by_user_id,
            'received_by_user_id' => $declaration->declared_by_user_id, 'registered_by_user_id' => $actor->id,
            'method' => $component->method, 'origin' => 'collection_declaration', 'source' => 'collection_declaration',
            'amount' => Decimal::format($amount), 'unallocated_amount' => Decimal::format($amount), 'currency' => 'ARS',
            'status' => 'confirmed', 'received_at' => $declaration->declared_at, 'confirmed_at' => now(),
            'reference' => $component->reference, 'external_reference' => $component->reference,
        ]);
        $this->allocate($payment, null, $declaration->visit->sale_id, false);
        if (Decimal::toInteger($payment->fresh()->unallocated_amount) > 0) {
            CollectionIncident::create([
                'tenant_id' => $tenant->id, 'route_id' => $declaration->visit->route_id, 'visit_id' => $declaration->visit_id,
                'declaration_id' => $declaration->id, 'type' => 'excess_pending_approval', 'status' => 'open',
                'details' => 'El cobro confirmado excede las obligaciones disponibles de la venta y requiere resolución administrativa.', 'reported_by' => $actor->id,
            ]);
        }

        return $payment->fresh();
    }

    private function refreshDeclarationStatus(Tenant $tenant, User $actor, string $declarationId): void
    {
        $declaration = CollectionDeclaration::forTenant($tenant)->whereKey($declarationId)->lockForUpdate()->firstOrFail();
        $pending = CollectionDeclarationComponent::forTenant($tenant)->where('declaration_id', $declarationId)->where('status', 'pending')->exists();
        if (! $pending) {
            $declaration->update(['status' => 'approved', 'approved_by_user_id' => $actor->id, 'approved_at' => now()]);
        }
    }

    private function allocate(Payment $payment, ?array $requested, ?string $saleId = null, bool $createCredit = true): void
    {
        $tenantId = $payment->tenant_id;
        $available = Decimal::toInteger($payment->amount);
        $targets = $requested === null ? CustomerAccountEntry::forTenant($tenantId)->where('customer_id', $payment->customer_id)->when($saleId, fn ($query) => $query->where('sale_id', $saleId))->where('status', 'open')->where('outstanding_amount', '>', 0)->orderByRaw('CASE WHEN due_date <= CURRENT_DATE THEN 0 ELSE 1 END')->orderBy('due_date')->get()->map(fn ($entry) => ['account_entry_id' => $entry->id])->all() : $requested;
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
        if ($available > 0 && $createCredit) {
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
