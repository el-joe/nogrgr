<?php

namespace App\Modules\AfterSales\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * ADM-090 reason modal.
 */
final class ReturnReasonData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name  locale => label
     */
    public function __construct(
        public readonly array $name,
        public readonly bool $requiresVideo,
        public readonly bool $sellerFault,
        public readonly bool $isActive,
        public readonly int $sortOrder,
    ) {}
}
