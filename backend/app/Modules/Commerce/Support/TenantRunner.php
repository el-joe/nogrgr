<?php

namespace App\Modules\Commerce\Support;

use App\Modules\Platform\Models\Tenant;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;

/**
 * Runs code in a store's tenant context (admin order pages read and change the store's
 * order in its own database). Already in that store → runs directly; otherwise the
 * previous context is restored afterwards, also when the callback throws.
 */
class TenantRunner
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(int $tenantId, Closure $callback): mixed
    {
        if (tenancy()->initialized && (int) tenant()?->getTenantKey() === $tenantId) {
            return $callback();
        }

        $tenant = tenancy()->find($tenantId);

        if (! $tenant instanceof TenantContract) {
            throw (new ModelNotFoundException)->setModel(Tenant::class, [$tenantId]);
        }

        $original = tenancy()->initialized ? tenant() : null;
        tenancy()->initialize($tenant);

        try {
            return $callback();
        } finally {
            // stancl's Tenant::run() does not restore the context when the callback throws.
            $original instanceof TenantContract ? tenancy()->initialize($original) : tenancy()->end();
        }
    }

    public function currentTenantId(): ?int
    {
        return tenancy()->initialized ? (int) tenant()?->getTenantKey() : null;
    }
}
