<?php

namespace App\Modules\Affiliates\Listeners;

use App\Modules\Affiliates\Services\ConversionService;
use App\Modules\Billing\Events\AffiliateConversionReversalRequested;

/**
 * Decision #46: a refunded subscription payment reverses the conversion earned on it.
 */
class ReverseConversionOnRefund
{
    public function __construct(private readonly ConversionService $conversions) {}

    public function handle(AffiliateConversionReversalRequested $event): void
    {
        $this->conversions->reverseForPaymentLog($event->paymentLogId, $event->reason);
    }
}
