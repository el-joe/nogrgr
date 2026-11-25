<?php

namespace App\Modules\Identity\Repositories\Contracts;

use App\Modules\Identity\Models\Customer;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Tenant-DB repository: call inside tenant context.
 *
 * @extends RepositoryContract<Customer>
 */
interface CustomerRepositoryContract extends RepositoryContract
{
    public function findByEmail(string $email): ?Customer;
}
