<?php

namespace App\Modules\Platform\Events;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Models\VendorRegistration;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A central-website sign-up finished its store step; provisioning has been queued.
 */
final class RegistrationCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly VendorRegistration $registration, public readonly Tenant $tenant) {}
}
