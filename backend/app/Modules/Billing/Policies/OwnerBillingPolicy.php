<?php

namespace App\Modules\Billing\Policies;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Models\Owner;
use App\Modules\Platform\Models\Tenant;

/**
 * Owner portal billing: an active owner sees and renews only the subscriptions and
 * invoices of stores they own (gates owner.subscriptions.manage / owner.invoices.view).
 */
final class OwnerBillingPolicy
{
    public function subscription(mixed $user, Subscription $subscription): bool
    {
        return $this->ownsTenant($user, (int) $subscription->tenant_id);
    }

    public function invoice(mixed $user, Invoice $invoice): bool
    {
        return $this->ownsTenant($user, (int) $invoice->tenant_id);
    }

    private function ownsTenant(mixed $user, int $tenantId): bool
    {
        if (! $user instanceof Owner || ! $user->isActive()) {
            return false;
        }

        return Tenant::query()->whereKey($tenantId)->where('owner_id', $user->id)->exists();
    }
}
