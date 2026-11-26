<?php

namespace App\Modules\Affiliates\Listeners;

use App\Modules\Affiliates\Services\AffiliateAccountService;
use App\Modules\Platform\Events\TenantProvisioned;

/**
 * Every provisioned store gets its own affiliate account (once).
 */
class CreateVendorAffiliate
{
    public function __construct(private readonly AffiliateAccountService $accounts) {}

    public function handle(TenantProvisioned $event): void
    {
        $this->accounts->createForTenant($event->tenant);
    }
}
