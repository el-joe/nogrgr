<?php

namespace App\Modules\Integrations\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Gateway edit (ADM-100b): platform fee (basis points + fixed USD minor units) and
 * the vendor-payments / tenant-registration configs.
 */
final class PaymentGatewayData extends BaseDTO
{
    /**
     * @param  list<GatewayConfigData>  $configs
     */
    public function __construct(
        public readonly int $feePercentBps,
        public readonly int $feeFixedMinor,
        public readonly array $configs,
    ) {}
}
