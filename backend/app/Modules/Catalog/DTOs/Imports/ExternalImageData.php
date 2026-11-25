<?php

namespace App\Modules\Catalog\DTOs\Imports;

use App\Shared\DTOs\BaseDTO;

/**
 * A gallery image of an external product (driver-neutral). Order = gallery order.
 */
final class ExternalImageData extends BaseDTO
{
    public function __construct(
        public readonly int $externalId,
        public readonly string $url,
        public readonly bool $isPrimary = false,
    ) {}
}
