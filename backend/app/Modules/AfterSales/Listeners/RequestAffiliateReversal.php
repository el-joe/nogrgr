<?php

namespace App\Modules\AfterSales\Listeners;

use App\Modules\AfterSales\Enums\RefundMethod;
use App\Modules\AfterSales\Events\RefundCompleted;
use App\Modules\Billing\Events\AffiliateConversionReversalRequested;
use App\Modules\Commerce\Enums\PaymentSource;

/**
 * Decision #46 / #69: a completed gateway refund of a platform-collected store order asks
 * the Affiliates ledger to reverse the conversion earned on that payment. Store orders
 * have no Billing payment log, so the key is the refund id and the reason names the
 * order ("store_order:<tenant>:<order uuid>").
 */
class RequestAffiliateReversal
{
    public function handle(RefundCompleted $event): void
    {
        $refund = $event->refund;

        if ($refund->method !== RefundMethod::OriginalPayment || $refund->payment_source !== PaymentSource::Central) {
            return;
        }

        AffiliateConversionReversalRequested::dispatch($refund->id, $refund->tenant_id, $refund->amount, $refund->currency, 'store_order:'.$refund->tenant_id.':'.$refund->order_uuid);
    }
}
