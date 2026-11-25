<?php

namespace App\Modules\Commerce\DTOs\Payments;

use App\Modules\Integrations\Enums\GatewayMode;
use App\Shared\DTOs\BaseDTO;

/**
 * VEN-078a form: switches, own keys (blank secret = keep the stored one) and mode.
 */
final class StoreGatewaySettingsData extends BaseDTO
{
    /**
     * @param  array<string, string|null>  $credentials
     */
    public function __construct(
        public readonly bool $isActive,
        public readonly bool $isHidden,
        public readonly bool $useOwn,
        public readonly GatewayMode $mode,
        public readonly array $credentials = [],
    ) {}
}
