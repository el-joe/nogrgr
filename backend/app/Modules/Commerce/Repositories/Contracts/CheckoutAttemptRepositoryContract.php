<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Commerce\Models\CheckoutAttempt;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * @extends RepositoryContract<CheckoutAttempt>
 */
interface CheckoutAttemptRepositoryContract extends RepositoryContract
{
    public function findByKey(string $key): ?CheckoutAttempt;

    /**
     * Inserts the attempt; null when the key already exists (concurrent / repeated submit).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function claim(array $attributes): ?CheckoutAttempt;
}
