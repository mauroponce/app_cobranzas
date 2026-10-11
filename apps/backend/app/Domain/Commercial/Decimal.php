<?php

namespace App\Domain\Commercial;

use Illuminate\Validation\ValidationException;

final class Decimal
{
    public static function toInteger(string|int $value, int $scale = 2): int
    {
        $value = (string) $value;
        if (! preg_match('/^\d+(?:\.\d{1,'.$scale.'})?$/D', $value)) {
            throw ValidationException::withMessages(['amount' => 'El importe o cantidad no tiene un formato válido.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        if (strlen($whole) > 12) {
            throw ValidationException::withMessages(['amount' => 'El importe excede el máximo permitido.']);
        }

        return (int) $whole * (10 ** $scale) + (int) str_pad($fraction, $scale, '0');
    }

    public static function format(int $value, int $scale = 2): string
    {
        $factor = 10 ** $scale;

        return intdiv($value, $factor).'.'.str_pad((string) ($value % $factor), $scale, '0', STR_PAD_LEFT);
    }

    public static function display(string|int $value): string
    {
        $normalized = self::format(self::toInteger((string) $value));
        [$whole, $fraction] = explode('.', $normalized);

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.$fraction;
    }

    public static function line(string $quantity, string $unitPrice): int
    {
        $milliunits = self::toInteger($quantity, 3);
        $cents = self::toInteger($unitPrice);
        if ($milliunits > 10000000 || $cents > 99999999999) {
            throw ValidationException::withMessages(['amount' => 'La cantidad o el precio excede el máximo permitido.']);
        }

        return intdiv($milliunits * $cents + 500, 1000);
    }
}
