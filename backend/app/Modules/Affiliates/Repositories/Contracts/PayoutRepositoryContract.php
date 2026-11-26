<?php

namespace App\Modules\Affiliates\Repositories\Contracts;

use App\Modules\Affiliates\Models\AffiliatePayout;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends RepositoryContract<AffiliatePayout>
 */
interface PayoutRepositoryContract extends RepositoryContract
{
    /**
     * Filters: search, method, affiliate_id.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<AffiliatePayout>
     */
    public function listing(array $filters = []): Builder;
}
