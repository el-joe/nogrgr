<?php

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An invited store team member accepted the invitation (set their password, VEN-076a).
 * Dispatched in the store's tenant context.
 */
final class VendorStaffJoined
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $staffId,
    ) {}
}
