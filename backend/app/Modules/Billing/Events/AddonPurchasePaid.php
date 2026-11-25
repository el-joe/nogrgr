<?php

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\AddonPurchase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A store's add-on purchase (e.g. a paid language) was paid; the owning module activates it.
 */
final class AddonPurchasePaid
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly AddonPurchase $purchase) {}
}
