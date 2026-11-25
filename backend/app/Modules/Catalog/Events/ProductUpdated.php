<?php

namespace App\Modules\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A central product was updated by an admin (fired after commit). Listeners: vendor
 * catalog sync and the AI translation delta (decision #44), later phases.
 */
final class ProductUpdated
{
    use Dispatchable;

    public function __construct(public readonly int $productId) {}
}
