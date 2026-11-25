<?php

namespace App\Modules\Catalog\DTOs\Vendor;

use App\Shared\DTOs\BaseDTO;

/**
 * Select markets modal (VEN-015a): countries the opportunity is sold in, each with its
 * placements (MarketPlacement values).
 */
final class MarketSelectionData extends BaseDTO
{
    /**
     * @param  array<int, list<string>>  $markets  country id => placements
     */
    public function __construct(public readonly array $markets) {}
}
