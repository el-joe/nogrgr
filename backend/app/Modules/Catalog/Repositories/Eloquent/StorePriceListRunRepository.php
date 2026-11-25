<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Models\StorePriceListRun;
use App\Modules\Catalog\Repositories\Contracts\StorePriceListRunRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<StorePriceListRun>
 */
class StorePriceListRunRepository extends BaseRepository implements StorePriceListRunRepositoryContract
{
    protected function model(): string
    {
        return StorePriceListRun::class;
    }

    public function advance(StorePriceListRun $run, int $processed): void
    {
        $run->forceFill(['processed' => $processed])->save();
    }
}
