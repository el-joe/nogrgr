<?php

namespace App\Modules\Integrations\DTOs;

use App\Modules\Integrations\Enums\PaymentOutcome;
use App\Shared\DTOs\BaseDTO;

/**
 * A verified gateway webhook reduced to what Billing needs: the gateway's event id
 * (for replay protection), the checkout it is about and its outcome. `checkoutId` is
 * null for events we do not handle; `needsConfirmation` means the payer approved but
 * the payment still has to be captured (PayPal CHECKOUT.ORDER.APPROVED). `refunds` lists
 * the refunds a refund event reports (decision #72); such events have no checkout id.
 * `transactionId` is the captured payment's id when the event carries it (Stripe Checkout
 * session → its payment_intent), so refunds work for payments settled by webhook.
 */
final class WebhookNotification extends BaseDTO
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $type,
        public readonly ?string $checkoutId,
        public readonly PaymentOutcome $outcome,
        public readonly bool $needsConfirmation = false,
        public readonly ?string $reason = null,
        /** @var list<RefundNotification> */
        public readonly array $refunds = [],
        public readonly ?string $transactionId = null,
    ) {}

    public function isRefundEvent(): bool
    {
        return $this->refunds !== [];
    }

    public function isRelevant(): bool
    {
        return $this->checkoutId !== null;
    }
}
