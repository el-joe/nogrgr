<?php

namespace App\Modules\Storefront\Http\Controllers\StoreApi\Concerns;

use App\Modules\Api\Support\StoreApiCustomer;
use App\Modules\Identity\Models\Customer;
use App\Modules\Storefront\Services\StorefrontContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Store API catalog request context: the shopping country (`X-Country` / `X-Country-Code`,
 * else the customer's default address, visitor country, store default) and the display
 * currency (`X-Currency`) drive the same storefront services as the themes; a bearer-token
 * customer is put on the `customer` guard so favourites and the address country apply.
 */
trait PreparesCatalogContext
{
    protected function prepareCatalog(Request $request): ?Customer
    {
        $customer = StoreApiCustomer::resolve($request);

        if ($customer instanceof Customer) {
            Auth::guard('customer')->setUser($customer);
        }

        $country = $request->header('X-Country') ?? $request->header('X-Country-Code');
        $currency = $request->header('X-Currency');
        app(StorefrontContext::class)->useApiPreferences(is_string($country) ? $country : null, is_string($currency) ? $currency : null);

        return $customer;
    }
}
