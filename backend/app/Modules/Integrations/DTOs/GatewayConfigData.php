<?php

namespace App\Modules\Integrations\DTOs;

use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Shared\DTOs\BaseDTO;

/**
 * One purpose of a gateway edit form. `credentials` only holds submitted values:
 * blank secret fields keep the stored ones.
 */
final class GatewayConfigData extends BaseDTO
{
    /**
     * @param  array<string, string>  $credentials
     * @param  list<string>  $currencies
     */
    public function __construct(
        public readonly GatewayPurpose $purpose,
        public readonly bool $isEnabled,
        public readonly GatewayMode $mode,
        public readonly array $credentials,
        public readonly array $currencies,
    ) {}
}
