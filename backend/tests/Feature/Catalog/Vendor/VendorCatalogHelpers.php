<?php

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Models\Tenant;

/*
| Shared setup of the vendor catalog tests (VEN-010 … 016): a store selling in SA + AE
| with a 20 % default markup, its owner, and helpers to add central products to it.
*/

if (! function_exists('catalogStore')) {
    /**
     * @return array{0: Tenant, 1: VendorStaff, 2: Country, 3: Country}
     */
    function catalogStore(string $slug = 'acme', int $profitBps = 2000): array
    {
        $sa = Country::query()->where('iso2', 'SA')->first() ?? Country::factory()->iso('SA')->create();
        $ae = Country::query()->where('iso2', 'AE')->first() ?? Country::factory()->iso('AE')->create();
        $tenant = test()->createTenant($slug, ['selling_country_ids' => [$sa->id, $ae->id], 'profit_percent_bps' => $profitBps]);
        $owner = test()->createVendorOwner($tenant);

        return [$tenant, $owner, $sa, $ae];
    }

    /**
     * Adds a central product to the store (a store_products row only).
     *
     * @param  array<string, mixed>  $attributes
     */
    function storeProduct(Tenant $tenant, int $centralProductId, array $attributes = []): StoreProduct
    {
        return $tenant->run(fn (): StoreProduct => StoreProduct::query()->create(array_merge([
            'source' => StoreProductSource::Central,
            'central_product_id' => $centralProductId,
            'is_active' => true,
            'imported_at' => now(),
        ], $attributes)));
    }

    /**
     * A staff member of $tenant with only the given permissions.
     *
     * @param  list<string>  $permissions
     */
    function catalogStaff(Tenant $tenant, array $permissions): VendorStaff
    {
        return $tenant->run(function () use ($permissions): VendorStaff {
            $staff = VendorStaff::factory()->create();
            $staff->givePermissionTo(['dashboard.view', ...$permissions]);

            return $staff;
        });
    }
}
