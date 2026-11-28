<?php

namespace App\Modules\Commerce\Services\Cart;

use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\Enums\DeliveryScope;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Models\StoreProductVariant;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Catalog\Services\Vendor\StoreProductPricingService;
use App\Modules\Commerce\DTOs\Cart\CartLine;
use App\Modules\Commerce\DTOs\Promotions\FlashDiscount;
use App\Modules\Commerce\DTOs\Promotions\ProductRef;
use App\Modules\Commerce\Enums\CartLineIssue;
use App\Modules\Commerce\Models\CartItem;
use App\Modules\Commerce\Pricing\OrderLineInput;
use App\Modules\Commerce\Services\Promotions\FlashSaleCatalog;
use App\Modules\Commerce\Services\Stock\StockReservationService;
use App\Modules\Localization\Services\StoreCountries;
use Illuminate\Database\Eloquent\Collection;

/**
 * Turns cart items into priced, validated lines for one country. Product data is read
 * through StoreCatalogReader (central ⊕ override, decision #65), unit prices (for the
 * shopper's price country; availability for the shipping country) through
 * StoreProductPricingService / UnitPricing, flash sales through FlashSaleCatalog. A line
 * is purchasable when the product is active for the store, sellable in the country
 * (store selling countries; own products also their country_ids) and in stock.
 */
class CartLineResolver
{
    public function __construct(
        private readonly StoreCatalogReader $reader,
        private readonly StoreProductPricingService $pricing,
        private readonly FlashSaleCatalog $flashSales,
        private readonly StockReservationService $stock,
        private readonly StoreCountries $countries,
    ) {}

    /**
     * @param  iterable<CartItem>  $items
     * @param  array<string, int>  $quantityOverrides  line key => quantity to validate instead of the stored one
     * @return list<CartLine>
     */
    public function resolve(iterable $items, ?int $countryId, array $quantityOverrides = [], ?int $priceCountryId = null): array
    {
        $items = collect($items)->values();

        if ($items->isEmpty()) {
            return [];
        }

        /** @var Collection<int, StoreProduct> $products */
        $products = new Collection($items->map(static fn (CartItem $item): ?StoreProduct => $item->storeProduct)->filter()->unique('id')->values()->all());
        $views = [];

        foreach ($this->reader->views($products) as $view) {
            $views[$view->id()] = $view;
        }

        $refs = [];
        $ids = [StoreProductSource::Central->value => [], StoreProductSource::Own->value => []];

        foreach ($views as $view) {
            $ref = $this->ref($view);
            $refs[$view->id()] = $ref;
            $ids[$ref->source->value][] = $ref->id;
        }

        $sellable = [
            StoreProductSource::Central->value => $this->stock->sellable(StoreProductSource::Central, $ids[StoreProductSource::Central->value]),
            StoreProductSource::Own->value => $this->stock->sellable(StoreProductSource::Own, $ids[StoreProductSource::Own->value]),
        ];
        $priceCountryId ??= $countryId;
        $flash = $priceCountryId !== null ? $this->flashSales->discountsFor(array_values($refs), $priceCountryId) : [];

        $lines = [];

        foreach ($items as $item) {
            /** @var CartItem $item */
            $view = $views[$item->store_product_id] ?? null;
            $quantity = $quantityOverrides[$item->lineKey()] ?? $item->quantity;
            $lines[] = $view === null
                ? new CartLine($item, ProductRef::own($item->store_product_id), null, '#'.$item->store_product_id, null, null, null, 0, null, null, null, CartLineIssue::Inactive)
                : $this->line($item, $view, $refs[$view->id()], $quantity, $countryId, $priceCountryId, $sellable[$refs[$view->id()]->source->value], $flash[$refs[$view->id()]->key()] ?? null);
        }

        return $lines;
    }

    /**
     * @param  array<string, int|null>  $sellable
     */
    private function line(CartItem $item, StoreProductView $view, ProductRef $ref, int $quantity, ?int $countryId, ?int $priceCountryId, array $sellable, ?FlashDiscount $flash): CartLine
    {
        $variantId = $item->variantId();
        $ownVariant = $view->isOwn() && $variantId !== null ? $view->product->variants->firstWhere('id', $variantId) : null;
        $centralVariant = ! $view->isOwn() && $variantId !== null ? $view->variant($variantId) : null;
        $variantLabel = $ownVariant instanceof StoreProductVariant ? $ownVariant->title : $centralVariant?->label;
        $image = $centralVariant->imageUrl ?? $view->primaryImageUrl();
        $available = $sellable[$ref->id.':'.$item->variant_key] ?? null;
        $stockKnown = array_key_exists($ref->id.':'.$item->variant_key, $sellable);

        $make = fn (?CartLineIssue $issue, ?OrderLineInput $input = null, $display = null): CartLine => new CartLine(
            $item, $ref, $view, $view->name, $variantLabel, $view->sku, $image, $stockKnown ? $available : 0, $flash, $issue === null ? $input : null, $display, $issue,
        );

        if (! $view->product->is_active || ! $view->available) {
            return $make(CartLineIssue::Inactive);
        }

        $variantMissing = $variantId !== null
            ? ($view->isOwn() ? ! ($ownVariant instanceof StoreProductVariant && $ownVariant->is_active) : $centralVariant === null)
            : (! $view->isOwn() && $view->variants !== []) || ($view->isOwn() && $view->product->variants->where('is_active', true)->isNotEmpty());

        if ($variantMissing || ! $stockKnown) {
            return $make(CartLineIssue::VariantUnavailable);
        }

        if ($countryId === null || ! $this->countries->isAvailable($countryId)) {
            return $make(CartLineIssue::NotSoldInCountry);
        }

        $flashPct = $flash->percentage ?? 0.0;

        if ($view->isOwn()) {
            $allowed = $view->product->country_ids;

            if ($allowed !== null && $allowed !== [] && ! in_array($countryId, array_map('intval', $allowed), true)) {
                return $make(CartLineIssue::NotSoldInCountry);
            }

            $sell = $ownVariant instanceof StoreProductVariant ? ($ownVariant->ownSellPrice() ?? $view->product->ownSellPrice()) : $view->product->ownSellPrice();
            $input = OrderLineInput::own($item->lineKey(), $sell, $quantity, $flashPct, $flash?->source);
            $display = $sell;
        } else {
            $unit = $this->pricing->unitFor($view->product, $variantId, $priceCountryId ?? $countryId) ?? $this->pricing->unitFor($view->product, $variantId, $countryId);

            if ($unit === null) {
                return $make(CartLineIssue::NotSoldInCountry);
            }

            $weight = $centralVariant !== null ? $centralVariant->weightGrams : $view->weightGrams;
            // Old isShippableCartItem: digital / non-shipping products take no part in the shipping charge.
            $shippable = $view->central === null || ($view->central->requires_shipping && $view->central->delivery_scope !== DeliveryScope::Digital);
            $input = OrderLineInput::catalog($item->lineKey(), $unit, $quantity, $shippable ? $weight : 0, $flashPct, $flash?->source, $shippable);
            $display = $unit->displayPrice();
        }

        if ($available !== null && $available <= 0) {
            return $make(CartLineIssue::OutOfStock, $input, $display);
        }

        if ($available !== null && $available < $quantity) {
            return $make(CartLineIssue::InsufficientStock, $input, $display);
        }

        return $make(null, $input, $display);
    }

    private function ref(StoreProductView $view): ProductRef
    {
        return $view->isOwn() ? ProductRef::own($view->id()) : ProductRef::central((int) $view->product->central_product_id);
    }
}
