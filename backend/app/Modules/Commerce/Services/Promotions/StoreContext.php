<?php

namespace App\Modules\Commerce\Services\Promotions;

use App\Modules\Commerce\Exceptions\PromotionException;
use App\Modules\Platform\Models\Tenant;

/**
 * The store whose promotions are read: the initialized tenant (its DB holds the store
 * rows, the central DB the admin rows). Readers refuse to run outside a store.
 */
final class StoreContext
{
    public static function tenant(): Tenant
    {
        $tenant = tenancy()->initialized ? tenant() : null;

        if (! $tenant instanceof Tenant) {
            throw PromotionException::storeContextRequired();
        }

        return $tenant;
    }

    /**
     * A central target country list trimmed to the store's selling countries.
     * [] = every country; null = the store sells in none of them (not applicable).
     *
     * @param  list<int>  $countryIds
     * @return list<int>|null
     */
    public static function trimCountries(Tenant $tenant, array $countryIds): ?array
    {
        $selling = $tenant->selling_country_ids;

        if ($countryIds === [] || $selling === null || $selling === []) {
            return $countryIds;
        }

        $kept = array_values(array_intersect($countryIds, array_map('intval', $selling)));
        sort($kept);

        return $kept === [] ? null : $kept;
    }
}
