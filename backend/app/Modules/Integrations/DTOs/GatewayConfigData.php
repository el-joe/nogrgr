<?php

namespace App\Modules\Integrations\DTOs;

use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Shared\DTOs\BaseDTO;

/**
 * One purpose of a gateway edit form. `credentials` only holds submitted values:
 * blank secret fields keep the stored ones. No currencies: gateways charge USD only (#87).
 */
final class GatewayConfigData extends BaseDTO
{
    /**
     * @param  array<string, string>  $credentials
     */
    public function __construct(
        public readonly GatewayPurpose $purpose,
        public readonly bool $isEnabled,
        public readonly GatewayMode $mode,
        public readonly array $credentials,
    ) {}
}
