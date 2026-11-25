<?php

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Shared\DTOs\BaseDTO;
use App\Shared\Money\Money;

/**
 * A hosted-checkout payment to start: gateway, purpose, USD amount and what the payer
 * sees. `meta` is kept on the payment log payload (e.g. registration_id) so the
 * purpose's listener can find what was paid for.
 */
final class StartPaymentData extends BaseDTO
{
    /**
     * @param  array<string, int|string|null>  $meta
     */
    public function __construct(
        public readonly PaymentGatewayCode $gateway,
        public readonly PaymentPurpose $purpose,
        public readonly Money $amount,
        public readonly string $description,
        public readonly ?string $customerEmail = null,
        public readonly ?string $locale = null,
        public readonly ?int $tenantId = null,
        public readonly array $meta = [],
    ) {}
}
