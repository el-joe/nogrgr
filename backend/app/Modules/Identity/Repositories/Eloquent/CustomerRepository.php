<?php

namespace App\Modules\Identity\Repositories\Eloquent;

use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<Customer>
 */
class CustomerRepository extends BaseRepository implements CustomerRepositoryContract
{
    protected function model(): string
    {
        return Customer::class;
    }

    public function findByEmail(string $email): ?Customer
    {
        return $this->query()->where('email', mb_strtolower(trim($email)))->first();
    }
}
