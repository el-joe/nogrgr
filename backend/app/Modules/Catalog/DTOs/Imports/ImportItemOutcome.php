<?php

namespace App\Modules\Catalog\DTOs\Imports;

use App\Modules\Catalog\Enums\ImportLogResult;
use App\Shared\DTOs\BaseDTO;

/**
 * What importing one external product did: the log result + message, the product it
 * touched, image counters for the progress bar and non-fatal warnings.
 */
final class ImportItemOutcome extends BaseDTO
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly ImportLogResult $result,
        public readonly string $message,
        public readonly ?int $productId = null,
        public readonly int $imagesTotal = 0,
        public readonly int $imagesDone = 0,
        public readonly array $warnings = [],
    ) {}
}
