<?php

namespace App\Modules\Storefront\Services\Checkout;

use App\Modules\Commerce\DTOs\Cart\CartContextData;
use App\Modules\Commerce\Http\Controllers\StoreApi\CartController as StoreApiCart;
use App\Modules\Commerce\Http\Resources\StoreApi\CartResource;
use App\Modules\Commerce\Services\Cart\CartService;
use App\Modules\Commerce\Support\MoneyPresenter;
use App\Modules\Identity\Models\Customer;
use App\Modules\Storefront\Services\StorefrontContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Server-side first paint of the theme cart (cart page, mini cart, checkout): the same
 * Store API cart (CartService + CartResource, never re-priced here), the theme session
 * cart token, the signed-in customer and the StorefrontContext display currency. A cart
 * without a country gets the storefront context country first (like PUT cart/country),
 * so totals are always quoted for the country the visitor shops in.
 */
class StorefrontCart
{
    public function __construct(
        private readonly CartService $carts,
        private readonly StorefrontContext $context,
        private readonly MoneyPresenter $money,
    ) {}

    public function context(Request $request): CartContextData
    {
        $token = $request->hasSession() ? $request->session()->get(StoreApiCart::CART_TOKEN_SESSION_KEY) : null;
        $customer = Auth::guard('customer')->user();
        $currency = $this->context->currencyCode();
        $this->money->useDisplayCurrency($currency);

        return new CartContextData(
            token: is_string($token) && $token !== '' ? $token : null,
            customer: $customer instanceof Customer ? $customer : null,
            displayCurrency: $currency,
        );
    }

    /**
     * The Store API cart payload (CartResource) for the current visitor.
     *
     * @return array<string, mixed>
     */
    public function data(Request $request): array
    {
        $context = $this->context($request);
        $cart = $this->carts->current($context);
        $countryId = $this->context->countryId();

        if ($cart !== null && $cart->country_id === null && $countryId !== null) {
            $summary = $this->carts->switchCountry($context, $countryId);
        } else {
            $summary = $this->carts->summary($context);
        }

        if ($request->hasSession() && $summary->cart?->token !== null) {
            $request->session()->put(StoreApiCart::CART_TOKEN_SESSION_KEY, $summary->cart->token);
        }

        return (new CartResource($summary))->toArray($request);
    }

    /**
     * A Store API money object in the display currency (`display.formatted`, else USD).
     *
     * @param  array<string, mixed>|null  $money
     */
    public static function price(?array $money): string
    {
        if ($money === null) {
            return '';
        }

        return (string) ($money['display']['formatted'] ?? $money['formatted'] ?? '');
    }

    /**
     * "You will be charged $X USD." when the shopper sees another currency: USD is the only
     * payment currency, other currencies are display-only conversions (decision #87).
     *
     * @param  array<string, mixed>|null  $money  MoneyPresenter output
     */
    public static function usdCharge(?array $money): ?string
    {
        $display = $money['display']['currency'] ?? null;

        if ($money === null || $display === null || $display === ($money['currency'] ?? 'USD')) {
            return null;
        }

        return __st('checkout.charged_usd', ['amount' => (string) ($money['formatted'] ?? '')]);
    }

    /**
     * @param  array<string, mixed>|null  $money
     */
    public static function positive(?array $money): bool
    {
        return (int) ($money['amount'] ?? 0) > 0;
    }

    /**
     * Lines that block checkout (unavailable, out of stock, not sold in the country).
     * Shipping is checked on the checkout page, for the chosen address.
     *
     * @param  array<string, mixed>  $cart
     */
    public static function blocked(array $cart): bool
    {
        foreach ($cart['lines'] as $line) {
            if ($line['issue'] !== null) {
                return true;
            }
        }

        return false;
    }
}
