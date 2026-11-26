<?php

namespace App\Modules\Affiliates\Repositories\Contracts;

use App\Modules\Affiliates\Models\AffiliatePromoCode;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends RepositoryContract<AffiliatePromoCode>
 */
interface PromoCodeRepositoryContract extends RepositoryContract
{
    /**
     * Filters: search, affiliate_id, state (active|inactive|expired). Rows carry conversions_count.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<AffiliatePromoCode>
     */
    public function listing(array $filters = []): Builder;

    public function findByCode(string $code): ?AffiliatePromoCode;

    public function hasConversions(AffiliatePromoCode $code): bool;
}
