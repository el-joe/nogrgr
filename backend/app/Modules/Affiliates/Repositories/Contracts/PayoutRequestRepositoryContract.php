<?php

namespace App\Modules\Affiliates\Repositories\Contracts;

use App\Modules\Affiliates\Models\AffiliatePayoutRequest;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends RepositoryContract<AffiliatePayoutRequest>
 */
interface PayoutRequestRepositoryContract extends RepositoryContract
{
    /**
     * Filters: search, status.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<AffiliatePayoutRequest>
     */
    public function listing(array $filters = []): Builder;

    /**
     * @return array<string, int> status => count
     */
    public function counts(): array;

    public function lock(int $id): AffiliatePayoutRequest;
}
