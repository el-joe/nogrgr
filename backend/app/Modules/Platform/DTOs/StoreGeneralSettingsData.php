<?php

namespace App\Modules\Platform\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Vendor › Settings › General (VEN-071): store name, shopper support e-mail, default
 * profit (basis points, the tenant fallback markup) and the store timezone.
 */
final class StoreGeneralSettingsData extends BaseDTO
{
    public function __construct(
        public readonly string $storeName,
        public readonly ?string $supportEmail,
        public readonly int $profitPercentBps,
        public readonly string $timezone,
    ) {}
}
