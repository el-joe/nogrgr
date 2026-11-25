<?php

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\PaymentLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A gateway payment failed.
 */
final class PaymentFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly PaymentLog $paymentLog) {}
}
