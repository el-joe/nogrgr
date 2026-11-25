<?php

namespace App\Modules\Integrations\DTOs;

use App\Modules\Integrations\Enums\RefundOutcome;
use App\Shared\DTOs\BaseDTO;

/**
 * The gateway's answer to a refund: completed, still pending (settles later) or failed.
 */
final class RefundResult extends BaseDTO
{
    /**
     * @param  array<string, mixed>  $details  safe subset of the gateway response
     */
    public function __construct(
        public readonly RefundOutcome $outcome,
        public readonly ?string $refundId = null,
        public readonly ?string $reason = null,
        public readonly array $details = [],
    ) {}
}
