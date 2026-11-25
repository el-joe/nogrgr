<?php

namespace App\Modules\AfterSales\Events;

use App\Modules\AfterSales\Models\Refund;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Money went back to the customer (gateway refund succeeded or staff marked it paid).
 */
final class RefundCompleted
{
    use Dispatchable;

    public function __construct(public readonly Refund $refund) {}
}
