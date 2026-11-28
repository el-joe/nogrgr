<?php

namespace App\Modules\Commerce\Services\Cart;

use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Commerce\DTOs\Cart\AddCartItemData;
use App\Modules\Commerce\DTOs\Cart\CartContextData;
use App\Modules\Commerce\DTOs\Cart\CartSummary;
use App\Modules\Commerce\Enums\CartLineIssue;
use App\Modules\Commerce\Enums\CouponRejection;
use App\Modules\Commerce\Events\CartItemAdded;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\CartItem;
use App\Modules\Commerce\Repositories\Contracts\CartRepositoryContract;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Services\CustomerAddressService;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Services\BaseService;
use Illuminate\Support\Str;

/**
 * Storefront cart (tenant DB) for guests (token) and customers. A guest cart sent with a
 * signed-in customer is merged into the customer's cart (quantities added, the guest
 * coupon kept when the customer has none) and deleted. Every change is validated by
 * CartLineResolver (active, sellable in the country, in stock) before it is written;
 * money always comes from CartSummaryService (OrderComposer).
 */
class CartService extends BaseService
{
    public const MAX_QUANTITY = 999;

    public function __construct(
        private readonly CartRepositoryContract $carts,
        private readonly CartSummaryService $summaries,
        private readonly CartLineResolver $lines,
        private readonly CustomerAddressService $addresses,
        private readonly StoreCountries $countries,
        private readonly StoreProductRepositoryContract $products,
    ) {}

    /**
     * The current cart (merging a guest cart into the customer's), or null when none exists.
     */
    public function current(CartContextData $context): ?Cart
    {
        $guest = $context->token !== null && $context->token !== '' ? $this->carts->findByToken($context->token) : null;

        if ($context->customer === null) {
            return $guest !== null && $guest->customer_id === null ? $guest : null;
        }

        $cart = $this->carts->findForCustomer($context->customer->id);

        if ($guest !== null && $guest->customer_id === null) {
            return $cart === null ? $this->adopt($guest, $context->customer) : $this->merge($guest, $cart);
        }

        return $cart;
    }

    /**
     * Lines are priced for the shopper's country; shipping, availability and coupons use
     * the shipping country (old CustomerCountryResolver: the given address country →
     * default saved address → the shopper's country).
     */
    public function summary(CartContextData $context): CartSummary
    {
        $cart = $this->current($context);

        return $this->withoutUnmetCoupon($this->summaries->summarize(
            $cart,
            $this->shippingCountry($cart, $context),
            $context->customer?->id,
            priceCountryId: $this->pricingCountry($cart, $context),
        ));
    }

    public function summaryOf(Cart $cart, CartContextData $context, ?int $shippingCountryId = null): CartSummary
    {
        return $this->withoutUnmetCoupon($this->summaries->summarize(
            $cart,
            $shippingCountryId ?? $this->shippingCountry($cart, $context),
            $context->customer?->id,
            priceCountryId: $this->pricingCountry($cart, $context),
        ));
    }

    public function add(CartContextData $context, AddCartItemData $data): CartSummary
    {
        $cart = $this->currentOrCreate($context);
        $variantKey = $data->variantId ?? 0;
        $existing = $this->carts->findLine($cart, $data->storeProductId, $variantKey);
        $quantity = min(self::MAX_QUANTITY, ($existing->quantity ?? 0) + max(1, $data->quantity));

        $this->assertProductExists($data->storeProductId);
        $this->assertPurchasable($cart, $context, new CartItem([
            'cart_id' => $cart->id,
            'store_product_id' => $data->storeProductId,
            'variant_key' => $variantKey,
            'quantity' => $quantity,
        ]));

        $this->carts->putLine($cart, $data->storeProductId, $variantKey, $quantity);
        $this->carts->touch($cart);

        if (tenancy()->initialized) {
            CartItemAdded::dispatch((int) tenant()->getTenantKey(), $data->storeProductId, max(1, $data->quantity));
        }

        return $this->summaryOf($cart->refresh(), $context);
    }

    public function updateQuantity(CartContextData $context, int $itemId, int $quantity): CartSummary
    {
        $cart = $this->current($context) ?? throw CommerceException::notFound('cart_not_found');
        $item = $this->carts->findItem($cart, $itemId) ?? throw CommerceException::notFound('item_not_found');

        if ($quantity <= 0) {
            $this->carts->deleteItem($item);
        } else {
            $item->quantity = min(self::MAX_QUANTITY, $quantity);
            $this->assertPurchasable($cart, $context, $item);
            $item->save();
        }

        $this->carts->touch($cart);

        return $this->summaryOf($cart->refresh(), $context);
    }

    public function remove(CartContextData $context, int $itemId): CartSummary
    {
        $cart = $this->current($context) ?? throw CommerceException::notFound('cart_not_found');
        $item = $this->carts->findItem($cart, $itemId) ?? throw CommerceException::notFound('item_not_found');
        $this->carts->deleteItem($item);
        $this->carts->touch($cart);

        return $this->summaryOf($cart->refresh(), $context);
    }

    public function applyCoupon(CartContextData $context, string $code): CartSummary
    {
        $cart = $this->current($context) ?? throw CommerceException::notFound('cart_not_found');
        $code = Str::upper(trim($code));
        $summary = $this->summaries->summarize($cart, $this->shippingCountry($cart, $context), $context->customer?->id, $code, priceCountryId: $this->pricingCountry($cart, $context));

        if ($summary->composed->isEmpty()) {
            throw CommerceException::notFound('cart_empty');
        }

        if ($summary->coupon === null || ! $summary->coupon->accepted()) {
            throw CommerceException::message((string) ($summary->coupon?->message() ?? __('commerce::coupons.rejections.not_found')), 'coupon_rejected');
        }

        $cart->forceFill(['coupon_code' => $code])->save();

        return $summary;
    }

    public function removeCoupon(CartContextData $context): CartSummary
    {
        $cart = $this->current($context) ?? throw CommerceException::notFound('cart_not_found');
        $cart->forceFill(['coupon_code' => null])->save();

        return $this->summaryOf($cart, $context);
    }

    public function switchCountry(CartContextData $context, int $countryId, ?int $cityId = null): CartSummary
    {
        if (! $this->countries->isAvailable($countryId)) {
            throw CommerceException::lineIssue(CartLineIssue::NotSoldInCountry);
        }

        $cart = $this->currentOrCreate($context);
        $cart->forceFill(['country_id' => $countryId, 'city_id' => $cityId])->save();

        return $this->summaryOf($cart, new CartContextData($context->token, $context->customer, $context->displayCurrency, $countryId));
    }

    /**
     * Removes the bought lines and the coupon after an order was placed.
     */
    public function clear(Cart $cart): void
    {
        $this->carts->clear($cart);
    }

    /**
     * Country the lines are priced for: the shopper's (switcher / detected / X-Country) →
     * the cart's chosen country → the customer's default address → the store default → the
     * first selling country.
     */
    public function pricingCountry(?Cart $cart, CartContextData $context): ?int
    {
        foreach ([$context->countryId, $cart?->country_id] as $countryId) {
            if ($countryId !== null && $this->countries->isAvailable($countryId)) {
                return $countryId;
            }
        }

        $default = $this->defaultAddressCountry($context->customer);

        if ($default !== null) {
            return $default;
        }

        $tenant = tenant();
        $storeDefault = $tenant instanceof Tenant ? $tenant->default_country_id : null;

        if ($storeDefault !== null && $this->countries->isAvailable($storeDefault)) {
            return $storeDefault;
        }

        $first = $this->countries->available()->first();

        return $first?->id;
    }

    /**
     * Country the cart ships to before an address is chosen at checkout: the customer's
     * default saved address, else the pricing country.
     */
    public function shippingCountry(?Cart $cart, CartContextData $context): ?int
    {
        return $this->defaultAddressCountry($context->customer) ?? $this->pricingCountry($cart, $context);
    }

    private function defaultAddressCountry(?Customer $customer): ?int
    {
        $default = $customer !== null ? $this->addresses->defaultFor($customer) : null;

        return $default !== null && $this->countries->isAvailable($default->country_id) ? $default->country_id : null;
    }

    /**
     * Old behaviour: a coupon whose minimum spend the cart no longer meets is removed (the
     * response still carries the reason).
     */
    private function withoutUnmetCoupon(CartSummary $summary): CartSummary
    {
        if ($summary->coupon?->rejection === CouponRejection::MinimumSpend && $summary->cart?->coupon_code !== null) {
            $summary->cart->forceFill(['coupon_code' => null])->save();
        }

        return $summary;
    }

    private function currentOrCreate(CartContextData $context): Cart
    {
        $cart = $this->current($context);

        if ($cart !== null) {
            return $cart;
        }

        /** @var Cart $cart */
        $cart = $this->carts->create([
            'token' => Str::random(48),
            'customer_id' => $context->customer?->id,
            'last_activity_at' => now(),
        ]);

        return $cart;
    }

    private function adopt(Cart $guest, Customer $customer): Cart
    {
        $guest->forceFill(['customer_id' => $customer->id])->save();

        return $guest;
    }

    private function merge(Cart $guest, Cart $cart): Cart
    {
        $this->transaction(function () use ($guest, $cart): void {
            foreach ($guest->items as $item) {
                $existing = $this->carts->findLine($cart, $item->store_product_id, $item->variant_key);
                $this->carts->putLine($cart, $item->store_product_id, $item->variant_key, min(self::MAX_QUANTITY, ($existing->quantity ?? 0) + $item->quantity));
            }

            if ($cart->coupon_code === null && $guest->coupon_code !== null) {
                $cart->forceFill(['coupon_code' => $guest->coupon_code])->save();
            }

            $this->carts->delete($guest);
        });

        return $cart->refresh();
    }

    private function assertProductExists(int $storeProductId): void
    {
        if (! $this->products->find($storeProductId) instanceof StoreProduct) {
            throw CommerceException::notFound('product_not_found');
        }
    }

    private function assertPurchasable(Cart $cart, CartContextData $context, CartItem $item): void
    {
        $item->setRelation('storeProduct', $this->products->find($item->store_product_id));
        [$line] = $this->lines->resolve([$item], $this->pricingCountry($cart, $context));

        if ($line->issue !== null) {
            throw new CommerceException($line->issue->message(), 422, $line->issue->value, ['available' => $line->available]);
        }
    }
}
