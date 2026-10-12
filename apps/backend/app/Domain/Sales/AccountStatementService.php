<?php

namespace App\Domain\Sales;

use App\Domain\Commercial\Decimal;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\LateFeePolicy;
use Carbon\CarbonImmutable;

final class AccountStatementService
{
    public function balances(Customer $customer, CarbonImmutable $asOf, ?string $sellerId = null): array
    {
        $entries = CustomerAccountEntry::forTenant($customer->tenant_id)->where('customer_id', $customer->id)->where('status', 'open')
            ->when($sellerId, fn ($q) => $q->whereHas('sale', fn ($sale) => $sale->where('seller_id', $sellerId)))->get();
        $contractual = 0;
        $due = 0;
        foreach ($entries as $entry) {
            $outstanding = Decimal::toInteger($entry->outstanding_amount);
            $contractual += $outstanding;
            if ($entry->due_date->toDateString() <= $asOf->toDateString()) {
                $due += $outstanding;
            }
        }

        return ['contractual' => Decimal::format($contractual), 'due' => Decimal::format($due)];
    }

    public function lateFee(CustomerAccountEntry $entry, CarbonImmutable $asOf): string
    {
        if ($entry->status !== 'open' || $entry->due_date->toDateString() >= $asOf->toDateString()) {
            return '0.00';
        }
        $policy = LateFeePolicy::forTenant($entry->tenant_id)->whereDate('effective_from', '<=', $entry->due_date)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $entry->due_date))->orderByDesc('version')->first();
        if (! $policy) {
            return '0.00';
        }
        $days = max(0, (int) CarbonImmutable::parse($entry->due_date->toDateString())->diffInDays(CarbonImmutable::parse($asOf->toDateString()), false) - $policy->grace_days);
        $rate = min($policy->daily_basis_points * $days, $policy->cap_basis_points);
        $cents = intdiv(Decimal::toInteger($entry->outstanding_amount) * $rate + 5000, 10000);

        return Decimal::format($cents);
    }
}
