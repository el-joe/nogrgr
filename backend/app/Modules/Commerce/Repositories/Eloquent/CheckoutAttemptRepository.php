<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Models\CheckoutAttempt;
use App\Modules\Commerce\Repositories\Contracts\CheckoutAttemptRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * @extends BaseRepository<CheckoutAttempt>
 */
class CheckoutAttemptRepository extends BaseRepository implements CheckoutAttemptRepositoryContract
{
    protected function model(): string
    {
        return CheckoutAttempt::class;
    }

    public function findByKey(string $key): ?CheckoutAttempt
    {
        return $this->query()->where('idempotency_key', $key)->first();
    }

    public function claim(array $attributes): ?CheckoutAttempt
    {
        try {
            /** @var CheckoutAttempt $attempt */
            $attempt = $this->query()->create($attributes);

            return $attempt;
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}
