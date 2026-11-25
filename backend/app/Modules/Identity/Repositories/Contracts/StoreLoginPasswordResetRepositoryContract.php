<?php

namespace App\Modules\Identity\Repositories\Contracts;

use App\Modules\Identity\Models\StoreLoginPasswordReset;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * @extends RepositoryContract<StoreLoginPasswordReset>
 */
interface StoreLoginPasswordResetRepositoryContract extends RepositoryContract
{
    public function findByEmail(string $email): ?StoreLoginPasswordReset;

    public function put(string $email, string $hashedToken): void;

    public function forget(string $email): void;
}
