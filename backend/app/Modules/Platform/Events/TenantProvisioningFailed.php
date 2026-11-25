<?php

namespace App\Modules\Platform\Events;

use App\Modules\Platform\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Provisioning stopped with an error; the store is "failed" and can be retried.
 */
final class TenantProvisioningFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Tenant $tenant, public readonly string $error) {}
}
