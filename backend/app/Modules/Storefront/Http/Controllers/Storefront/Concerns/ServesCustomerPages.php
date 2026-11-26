<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront\Concerns;

use App\Modules\AfterSales\Exceptions\AfterSalesException;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Identity\Models\Customer;
use App\Modules\Storefront\DTOs\SeoData;
use App\Modules\Storefront\Services\Seo\StorefrontSeo;
use Illuminate\Support\Facades\Auth;

/**
 * Signed-in customer pages (account, orders, returns, wishlist): the customer guard user,
 * "not yours / not found" from the order and return services as a themed 404, noindex SEO.
 */
trait ServesCustomerPages
{
    protected function customer(): Customer
    {
        $customer = Auth::guard('customer')->user();

        abort_unless($customer instanceof Customer, 403);

        return $customer;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function orNotFound(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (CommerceException|AfterSalesException $exception) {
            abort($exception->status === 404 ? 404 : 422, $exception->getMessage());
        }
    }

    protected function privateSeo(string $title): SeoData
    {
        return app(StorefrontSeo::class)->page($title, index: false);
    }
}
