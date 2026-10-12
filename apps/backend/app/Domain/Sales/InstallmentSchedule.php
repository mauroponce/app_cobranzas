<?php

namespace App\Domain\Sales;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class InstallmentSchedule
{
    public function generate(int $totalCents, int $count, string $frequency, CarbonImmutable $firstDueDate): array
    {
        if ($totalCents <= 0 || $count < 1 || $count > 120 || $count > $totalCents || ! in_array($frequency, ['daily', 'weekly', 'biweekly', 'monthly'], true)) {
            throw ValidationException::withMessages(['installments' => 'El cronograma solicitado no es válido.']);
        }
        $base = intdiv($totalCents, $count);
        $remainder = $totalCents % $count;
        $schedule = [];
        for ($sequence = 1; $sequence <= $count; $sequence++) {
            $dueDate = match ($frequency) {
                'daily' => $firstDueDate->addDays($sequence - 1),
                'weekly' => $firstDueDate->addWeeks($sequence - 1),
                'biweekly' => $firstDueDate->addDays(14 * ($sequence - 1)),
                'monthly' => $firstDueDate->addMonthsNoOverflow($sequence - 1),
            };
            $schedule[] = ['sequence' => $sequence, 'due_date' => $dueDate->toDateString(), 'amount_cents' => $base + ($sequence === $count ? $remainder : 0)];
        }

        return $schedule;
    }
}
