<?php

namespace App\Modules\Identity\Repositories\Eloquent;

use App\Modules\Identity\Models\StoreLoginPasswordReset;
use App\Modules\Identity\Repositories\Contracts\StoreLoginPasswordResetRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<StoreLoginPasswordReset>
 */
class StoreLoginPasswordResetRepository extends BaseRepository implements StoreLoginPasswordResetRepositoryContract
{
    protected function model(): string
    {
        return StoreLoginPasswordReset::class;
    }

    public function findByEmail(string $email): ?StoreLoginPasswordReset
    {
        return StoreLoginPasswordReset::query()->find($email);
    }

    public function put(string $email, string $hashedToken): void
    {
        StoreLoginPasswordReset::query()->updateOrCreate(['email' => $email], ['token' => $hashedToken, 'created_at' => now()]);
    }

    public function forget(string $email): void
    {
        StoreLoginPasswordReset::query()->whereKey($email)->delete();
    }
}
