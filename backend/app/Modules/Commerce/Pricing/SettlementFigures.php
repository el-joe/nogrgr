<?php

namespace App\Modules\Commerce\Pricing;

use App\Modules\Commerce\Enums\CollectedBy;
use App\Shared\Money\Money;

/**
 * Platform / vendor figures of one order (ORDER_FORMULAS.md §2–3), shown the same in the
 * admin and the vendor panel.
 */
final readonly class SettlementFigures
{
    public function __construct(
        public CollectedBy $collectedBy,
        public bool $void,
        public Money $platformShare,
        public Money $vendorShare,
        public Money $refunded,
        public Money $settlementsPaid,
        public Money $owedToPlatform,
        public Money $owedToVendor,
        public int $vendorPercent,
        public int $platformPercent,
    ) {}

    /**
     * The percentages describe the split made at checkout, so they are only shown while the
     * share amounts still are that split (no refund, not void).
     */
    public function showsPercentages(): bool
    {
        return ! $this->void && ! $this->refunded->isPositive();
    }
}
