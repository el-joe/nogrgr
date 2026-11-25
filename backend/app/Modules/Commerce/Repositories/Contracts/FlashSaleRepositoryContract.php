<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Commerce\Models\FlashSale;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Central flash sales (ADM-026): products, countries, stores.
 *
 * @extends RepositoryContract<FlashSale>
 */
interface FlashSaleRepositoryContract extends RepositoryContract
{
    /**
     * @param  list<int>  $productIds
     * @param  list<int>  $countryIds
     * @param  list<int>  $tenantIds
     */
    public function syncTargets(FlashSale $sale, array $productIds, array $countryIds, array $tenantIds): void;

    /**
     * Not-deleted central sales a store may use, optionally only those covering one of
     * the products. Countries and product ids eager loaded.
     *
     * @param  list<int>|null  $productIds
     * @return Collection<int, FlashSale>
     */
    public function applicableToTenant(int $tenantId, ?array $productIds = null, bool $activeOnly = false): Collection;

    /**
     * @return array{live: int, scheduled: int, ended: int}
     */
    public function tabCounts(): array;
}
