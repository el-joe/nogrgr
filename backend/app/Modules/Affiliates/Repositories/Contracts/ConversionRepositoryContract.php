<?php

namespace App\Modules\Affiliates\Repositories\Contracts;

use App\Modules\Affiliates\Models\AffiliateConversion;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<AffiliateConversion>
 */
interface ConversionRepositoryContract extends RepositoryContract
{
    /**
     * Filters: search, status, affiliate_id.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<AffiliateConversion>
     */
    public function listing(array $filters = []): Builder;

    /**
     * @return array<string, int> status => count
     */
    public function counts(): array;

    public function existsForTenant(int $tenantId): bool;

    public function lockPendingForTenant(int $tenantId): ?AffiliateConversion;

    public function lockByPaymentLog(int $paymentLogId): ?AffiliateConversion;

    public function paymentLogUsed(int $paymentLogId): bool;

    public function lock(int $id): AffiliateConversion;

    /**
     * Approved conversions with commission still unpaid, oldest first (locked).
     *
     * @return Collection<int, AffiliateConversion>
     */
    public function lockUnpaidApproved(int $affiliateId): Collection;
}
