<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Models\OrderSettlement;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A vendor → platform settlement of an order was paid (the vendor's "Pay platform"
 * capture, or an admin "mark settlement paid"). Central; listener: Billing ledger
 * (decision #72).
 */
final class SettlementPaid
{
    use Dispatchable;

    public function __construct(public readonly OrderSettlement $settlement) {}
}
