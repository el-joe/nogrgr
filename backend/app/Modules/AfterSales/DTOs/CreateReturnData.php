<?php

namespace App\Modules\AfterSales\DTOs;

use App\Modules\AfterSales\Enums\ReturnMethod;
use App\Modules\AfterSales\Enums\ReturnType;
use App\Shared\DTOs\BaseDTO;

/**
 * A return opened by staff for one order line (ADM-021c).
 */
final class CreateReturnData extends BaseDTO
{
    public function __construct(
        public readonly int $orderItemId,
        public readonly int $quantity,
        public readonly string $reasonCode,
        public readonly string $description,
        public readonly ReturnType $type = ReturnType::Return,
        public readonly ?ReturnMethod $returnMethod = null,
        public readonly ?string $customerNote = null,
        public readonly ?int $replacementVariantId = null,
        public readonly ?string $replacementLabel = null,
    ) {}
}
