<?php

namespace App\Modules\AfterSales\DTOs;

use App\Modules\AfterSales\Enums\ReturnShippingPayer;
use App\Shared\DTOs\BaseDTO;

/**
 * ADM-090 rules card.
 */
final class ReturnPolicyData extends BaseDTO
{
    /**
     * @param  list<int>  $nonReturnableCategoryIds
     */
    public function __construct(
        public readonly int $windowDays,
        public readonly int $restockingFeeBps,
        public readonly ReturnShippingPayer $returnShippingPayer,
        public readonly array $nonReturnableCategoryIds,
        public readonly bool $requireVideo,
        public readonly bool $allowExchanges,
    ) {}
}
