<?php

namespace App\Modules\Affiliates\Repositories\Contracts;

use App\Modules\Affiliates\Models\AffiliateSsoToken;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * @extends RepositoryContract<AffiliateSsoToken>
 */
interface SsoTokenRepositoryContract extends RepositoryContract
{
    public function lockByHash(string $hash): ?AffiliateSsoToken;

    /**
     * Removes tokens that expired more than a day ago.
     */
    public function prune(): int;
}
