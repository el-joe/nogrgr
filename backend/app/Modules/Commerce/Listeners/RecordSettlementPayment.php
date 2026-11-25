<?php

namespace App\Modules\Commerce\Listeners;

use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Events\PaymentCaptured;
use App\Modules\Billing\Events\PaymentFailed;
use App\Modules\Commerce\Services\Settlement\SettlementService;
use App\Modules\Integrations\Enums\PaymentOutcome;
use Illuminate\Events\Dispatcher;

/**
 * Settlement payments (purpose "settlement"): capture → the order settlement is paid
 * (vendor_own_platform drops), failure → failed (the vendor may pay again).
 */
class RecordSettlementPayment
{
    public function __construct(private readonly SettlementService $settlements) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(PaymentCaptured::class, [self::class, 'captured']);
        $events->listen(PaymentFailed::class, [self::class, 'failed']);
    }

    public function captured(PaymentCaptured $event): void
    {
        if ($event->paymentLog->purpose === PaymentPurpose::Settlement) {
            $this->settlements->applyOutcome($event->paymentLog, PaymentOutcome::Paid);
        }
    }

    public function failed(PaymentFailed $event): void
    {
        if ($event->paymentLog->purpose === PaymentPurpose::Settlement) {
            $this->settlements->applyOutcome($event->paymentLog, PaymentOutcome::Failed);
        }
    }
}
