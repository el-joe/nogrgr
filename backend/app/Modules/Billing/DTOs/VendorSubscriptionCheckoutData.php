<?php

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\VendorSubscriptionAction;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Shared\DTOs\BaseDTO;

/**
 * A vendor-panel checkout (VEN-060a): renew, change plan (plan + term) or pay an invoice.
 */
final class VendorSubscriptionCheckoutData extends BaseDTO
{
    public function __construct(
        public readonly VendorSubscriptionAction $action,
        public readonly PaymentGatewayCode $gateway,
        public readonly ?int $planId = null,
        public readonly ?BillingTerm $term = null,
        public readonly ?int $invoiceId = null,
    ) {}
}
