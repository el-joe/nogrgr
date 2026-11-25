<?php

namespace App\Modules\AfterSales\Events;

use App\Modules\AfterSales\Models\Refund;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A gateway refund was declined or could not be sent (staff can retry / complete / reject).
 */
final class RefundFailed
{
    use Dispatchable;

    public function __construct(public readonly Refund $refund) {}
}
