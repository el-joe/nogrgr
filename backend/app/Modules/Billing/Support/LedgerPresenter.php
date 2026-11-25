<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Enums\PayoutMethod;
use App\Modules\Billing\Enums\SettlementMethod;
use App\Modules\Billing\Models\LedgerTransaction;
use App\Shared\Money\Money;

/**
 * Translated title / subtitle of a ledger transaction from its stored meta (key + params),
 * falling back to the stored English description.
 */
final class LedgerPresenter
{
    public static function title(LedgerTransaction $transaction): string
    {
        return self::text('titles', $transaction) ?? $transaction->description;
    }

    public static function subtitle(LedgerTransaction $transaction): string
    {
        return self::text('subtitles', $transaction) ?? $transaction->source->label();
    }

    private static function text(string $group, LedgerTransaction $transaction): ?string
    {
        $meta = $transaction->meta ?? [];
        $key = is_string($meta['key'] ?? null) ? $meta['key'] : null;

        if ($key === null) {
            return null;
        }

        $params = is_array($meta['params'] ?? null) ? $meta['params'] : [];

        if (isset($params['total'])) {
            $params['total'] = Money::ofMinor((int) $params['total'])->format();
        }

        if (isset($params['method'])) {
            $params['method'] = (PayoutMethod::tryFrom((string) $params['method']) ?? SettlementMethod::tryFrom((string) $params['method']))?->label() ?? (string) $params['method'];
        } elseif (is_string($meta['method'] ?? null)) {
            $params['method'] = SettlementMethod::tryFrom($meta['method'])?->label() ?? (string) __('billing::finance.settlements.gateway_payment');
        }

        $line = 'billing::ledger.'.$group.'.'.$key;
        $text = __($line, array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $params));

        return is_string($text) && $text !== $line ? $text : null;
    }
}
