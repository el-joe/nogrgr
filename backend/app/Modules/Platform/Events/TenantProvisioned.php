<?php

namespace App\Modules\Platform\Events;

use App\Modules\Platform\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A store finished provisioning (database, languages, owner, plan) and is active.
 */
final class TenantProvisioned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Tenant $tenant) {}
}
