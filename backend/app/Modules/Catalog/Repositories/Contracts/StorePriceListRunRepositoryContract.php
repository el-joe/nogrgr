<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Models\StorePriceListRun;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Markup apply runs of the price list modal (tenant DB).
 *
 * @extends RepositoryContract<StorePriceListRun>
 */
interface StorePriceListRunRepositoryContract extends RepositoryContract
{
    public function advance(StorePriceListRun $run, int $processed): void;
}
