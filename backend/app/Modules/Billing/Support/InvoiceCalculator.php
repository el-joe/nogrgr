<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\DTOs\InvoiceLineData;
use App\Shared\Money\Money;

/**
 * Invoice totals in Money (USD minor units):
 *   line total = unit × quantity
 *   subtotal   = Σ line totals
 *   tax        = subtotal × tax_rate_bp / 10000 (round half up to the cent, Money::percentage)
 *   total      = subtotal + tax
 */
final class InvoiceCalculator
{
    /**
     * @param  list<InvoiceLineData>  $lines
     * @return array{lines: list<array{line: InvoiceLineData, total: Money}>, subtotal: Money, tax: Money, total: Money}
     */
    public static function calculate(array $lines, int $taxRateBasisPoints = 0, string $currency = Money::BASE_CURRENCY): array
    {
        $subtotal = Money::zero($currency);
        $computed = [];

        foreach ($lines as $line) {
            $lineTotal = Money::ofMinor($line->unitAmountMinor, $currency)->multiply($line->quantity);
            $subtotal = $subtotal->add($lineTotal);
            $computed[] = ['line' => $line, 'total' => $lineTotal];
        }

        $tax = $taxRateBasisPoints > 0 ? $subtotal->percentage($taxRateBasisPoints / 100) : Money::zero($currency);

        return [
            'lines' => $computed,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal->add($tax),
        ];
    }
}
