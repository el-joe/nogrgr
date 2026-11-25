<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Identity\Models\VendorStaff;

/**
 * Store products (tenant DB): imported central products need `catalog.products.*`,
 * own products `catalog.own-products.*`. Route middleware already scopes every request to
 * the signed-in store; this policy keeps a central-products manager from editing own
 * products (and the other way round) through a crafted id.
 */
class StoreProductPolicy
{
    public function view(VendorStaff $staff, StoreProduct $product): bool
    {
        return $staff->can($this->resource($product).'.view');
    }

    public function update(VendorStaff $staff, StoreProduct $product): bool
    {
        return $staff->can($this->resource($product).'.manage');
    }

    public function delete(VendorStaff $staff, StoreProduct $product): bool
    {
        return $this->update($staff, $product);
    }

    private function resource(StoreProduct $product): string
    {
        return $product->isOwn() ? 'catalog.own-products' : 'catalog.products';
    }
}
