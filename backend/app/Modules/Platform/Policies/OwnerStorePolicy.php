<?php

namespace App\Modules\Platform\Policies;

use App\Modules\Identity\Models\Owner;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Models\Tenant;

/**
 * Owner portal ({central}/my-account): an active owner reaches only their own stores
 * and the custom domains of those stores (gates owner.portal / owner.stores.manage /
 * owner.domains.manage, defined in the Platform ModuleServiceProvider).
 */
final class OwnerStorePolicy
{
    public function portal(mixed $user): bool
    {
        return $user instanceof Owner && $user->isActive();
    }

    public function manageStore(mixed $user, Tenant $tenant): bool
    {
        return $this->portal($user) && (int) $tenant->getAttribute('owner_id') === $user->id;
    }

    public function manageDomain(mixed $user, DomainRequest $domain): bool
    {
        $tenant = $domain->tenant()->withoutGlobalScopes()->first();

        return $tenant instanceof Tenant && $this->manageStore($user, $tenant);
    }
}
