<?php

namespace App\Domain\Sales;

use App\Domain\Tenancy\Audit;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelSaleAction
{
    public function execute(Sale $sale, User $actor, string $reason): Sale
    {
        app(SalesAuthorization::class)->require($sale->tenant_id, $actor, 'sales.cancel');

        return DB::transaction(function () use ($sale, $actor, $reason): Sale {
            $sale = Sale::forTenant($sale->tenant_id)->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if ($sale->status !== 'confirmed' || $sale->plan?->installments()->whereColumn('amount', '!=', 'outstanding_amount')->exists() || $sale->accountEntries()->whereColumn('amount', '!=', 'outstanding_amount')->exists()) {
                throw ValidationException::withMessages(['status' => 'No se puede anular una venta con cobros o ya anulada.']);
            }
            $sale->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $sale->accountEntries()->update(['outstanding_amount' => '0.00', 'status' => 'cancelled']);
            $sale->plan?->installments()->update(['outstanding_amount' => '0.00', 'status' => 'cancelled']);
            Audit::record('sale.cancelled', $sale, $actor, $sale->tenant_id);

            return $sale;
        });
    }
}
