<?php

namespace App\Modules\Identity\Repositories\Contracts;

use App\Modules\Identity\Models\CustomerAddress;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tenant-DB repository: call inside tenant context. Every lookup is scoped to one customer.
 *
 * @extends RepositoryContract<CustomerAddress>
 */
interface CustomerAddressRepositoryContract extends RepositoryContract
{
    /**
     * Default first, then oldest first; central countries eager loaded.
     *
     * @return Collection<int, CustomerAddress>
     */
    public function forCustomer(int $customerId): Collection;

    public function findForCustomer(int $customerId, int $addressId): ?CustomerAddress;

    public function defaultFor(int $customerId): ?CustomerAddress;

    public function oldestFor(int $customerId): ?CustomerAddress;

    public function countFor(int $customerId): int;

    /**
     * Lock the customer's address rows for a default-address change.
     */
    public function lockForCustomer(int $customerId): void;

    /**
     * Un-default every address of the customer except $exceptId.
     */
    public function clearDefault(int $customerId, ?int $exceptId = null): int;

    public function deleteForCustomer(int $customerId): int;
}
