<?php

namespace App\Modules\AfterSales\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Vendor › Settings › Return policy (VEN-079): the store's stricter override of the
 * platform default and its storefront policy text per locale.
 */
final class StoreReturnPolicyData extends BaseDTO
{
    /**
     * @param  list<int>  $nonReturnableCategoryIds
     * @param  array<string, string>  $text  locale => policy text
     */
    public function __construct(
        public readonly int $windowDays,
        public readonly int $restockingFeeBps,
        public readonly array $nonReturnableCategoryIds,
        public readonly bool $allowExchanges,
        public readonly array $text,
    ) {}
}
