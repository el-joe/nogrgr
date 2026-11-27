<?php

namespace App\Modules\Platform\Repositories\Contracts;

use App\Modules\Platform\Models\CacheClearRun;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * @extends RepositoryContract<CacheClearRun>
 */
interface CacheClearRunRepositoryContract extends RepositoryContract
{
    /**
     * Latest run per action (with its admin): central actions when $tenantId is null,
     * else that store's actions.
     *
     * @param  list<string>  $actions
     * @return array<string, CacheClearRun>
     */
    public function latest(array $actions, ?int $tenantId = null): array;
}
