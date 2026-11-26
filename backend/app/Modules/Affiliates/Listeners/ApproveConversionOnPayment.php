<?php

namespace App\Modules\Affiliates\Listeners;

use App\Modules\Affiliates\Services\ConversionService;
use App\Modules\Billing\Events\PaymentCaptured;

/**
 * PaymentCaptured → approves the store's pending conversion on its first paid
 * subscription payment (ConversionService::approveForPayment, idempotent per log).
 */
class ApproveConversionOnPayment
{
    public function __construct(private readonly ConversionService $conversions) {}

    public function handle(PaymentCaptured $event): void
    {
        $this->conversions->approveForPayment($event->paymentLog);
    }
}
