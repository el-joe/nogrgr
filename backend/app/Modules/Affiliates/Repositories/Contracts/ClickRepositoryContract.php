<?php

namespace App\Modules\Affiliates\Repositories\Contracts;

use App\Modules\Affiliates\Models\AffiliateClick;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Support\Carbon;

/**
 * @extends RepositoryContract<AffiliateClick>
 */
interface ClickRepositoryContract extends RepositoryContract
{
    /**
     * An unconverted click made at or after $since.
     */
    public function findOpen(int $id, Carbon $since): ?AffiliateClick;
}
