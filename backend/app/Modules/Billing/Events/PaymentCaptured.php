<?php

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\PaymentLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A gateway payment was captured.
 */
final class PaymentCaptured
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly PaymentLog $paymentLog) {}
}
