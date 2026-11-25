<?php

namespace App\Modules\Integrations\DTOs;

use App\Modules\Integrations\Enums\RefundOutcome;
use App\Shared\DTOs\BaseDTO;

/**
 * One refund reported by a verified gateway webhook (Stripe `charge.refunded` /
 * `refund.*`, PayPal `PAYMENT.CAPTURE.REFUNDED`): the gateway's refund id, our refund
 * reference as sent with the refund call (`<reference>-<attempt>`, Stripe metadata /
 * PayPal invoice_id) and its outcome.
 */
final class RefundNotification extends BaseDTO
{
    public function __construct(
        public readonly ?string $refundId,
        public readonly ?string $reference,
        public readonly RefundOutcome $outcome,
        public readonly ?string $reason = null,
    ) {}
}
