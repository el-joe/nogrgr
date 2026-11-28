<?php

namespace App\Modules\Commerce\Http\Concerns;

use App\Modules\Api\Support\StoreApiCustomer;
use App\Modules\Commerce\DTOs\Cart\CartContextData;
use App\Modules\Commerce\Enums\CheckoutBlock;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Support\MoneyPresenter;
use App\Modules\Identity\Models\Customer;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Storefront\Services\StorefrontContext;
use Illuminate\Http\Request;

/**
 * Store API request context: guest cart token (`X-Cart-Token`, or the storefront session
 * for themes), the customer (Sanctum token or session) and the display currency.
 */
trait ResolvesStoreApiContext
{
    public const CART_TOKEN_HEADER = 'X-Cart-Token';

    public const CART_TOKEN_SESSION_KEY = 'store_api.cart_token';

    protected function cartContext(Request $request): CartContextData
    {
        $token = $request->header(self::CART_TOKEN_HEADER);

        if ((! is_string($token) || $token === '') && $request->hasSession()) {
            $token = $request->session()->get(self::CART_TOKEN_SESSION_KEY);
        }

        $currency = $request->header('X-Currency');
        app(MoneyPresenter::class)->useDisplayCurrency(is_string($currency) ? $currency : null);

        return new CartContextData(
            token: is_string($token) && $token !== '' ? $token : null,
            customer: StoreApiCustomer::resolve($request),
            displayCurrency: is_string($currency) ? $currency : null,
            countryId: $this->shopperCountryId($request),
        );
    }

    /**
     * The country the shopper browses in: `X-Country` (ISO2, apps); theme calls on the
     * store session use the storefront context, so the Store API quotes exactly what the
     * server-rendered pages show. Null lets the cart fall back to its own chain.
     */
    private function shopperCountryId(Request $request): ?int
    {
        $iso2 = $request->header('X-Country');

        if (is_string($iso2) && preg_match('/^[A-Za-z]{2}$/', $iso2) === 1) {
            return app(StoreCountries::class)->findByIso2(strtoupper($iso2))?->id;
        }

        return $request->hasSession() && ! $request->hasHeader('X-Tenant') && $request->bearerToken() === null
            ? app(StorefrontContext::class)->countryId()
            : null;
    }

    protected function requireCustomer(Request $request): Customer
    {
        return StoreApiCustomer::resolve($request) ?? throw CommerceException::blocked(CheckoutBlock::SignInRequired);
    }

    /**
     * Themes keep the guest cart in the session; apps read the token from the response.
     */
    protected function rememberCartToken(Request $request, ?string $token): void
    {
        if ($token !== null && $request->hasSession()) {
            $request->session()->put(self::CART_TOKEN_SESSION_KEY, $token);
        }
    }
}
