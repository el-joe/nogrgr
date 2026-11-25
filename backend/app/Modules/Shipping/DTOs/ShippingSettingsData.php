<?php

namespace App\Modules\Shipping\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Shipping defaults (ADM-034): days used when no company day rule applies, order cut-off,
 * weekly off days (skipped by the estimator) and storefront toggles.
 */
final class ShippingSettingsData extends BaseDTO
{
    /**
     * @param  list<int>  $weeklyOffDays
     */
    public function __construct(
        public readonly int $defaultMinDays = 2,
        public readonly int $defaultMaxDays = 5,
        public readonly ?string $cutoffTime = null,
        public readonly array $weeklyOffDays = [],
        public readonly bool $showEstimateOnProduct = true,
        public readonly bool $showDeliveryPopup = true,
    ) {}
}
