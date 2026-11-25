<?php

namespace App\Modules\Commerce\DTOs\Payments;

use App\Modules\Commerce\Models\StorePaymentGateway;
use App\Modules\Integrations\Enums\PaymentGatewayCode;

/**
 * One gateway of the store settings list (VEN-078): its store row (persisted or not yet)
 * and whether the storefront lists it right now.
 */
final readonly class StoreGatewayRow
{
    public function __construct(
        public PaymentGatewayCode $code,
        public StorePaymentGateway $row,
        public bool $ready,
        public string $state,
    ) {}
}
