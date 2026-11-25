<?php

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A customer created an account on a storefront (web or mobile). Dispatched after
 * the registration transaction commits, in the tenant's context.
 */
final class CustomerRegistered
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $customerId,
    ) {}
}
