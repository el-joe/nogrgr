<?php

namespace App\Modules\Commerce\Pricing;

use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Shipping\DTOs\QuoteLine;
use App\Modules\Shipping\DTOs\ShippingQuote;
use App\Modules\Shipping\DTOs\ShippingQuoteRequest;
use App\Modules\Shipping\Services\ShippingQuoteService;
use App\Shared\Money\Money;

/**
 * Cart → order(s), ORDER_FORMULAS.md §1–2 (old OrderComposer::create maths, no persistence):
 *
 *   1. every line is priced by OrderPricing::item() (fixed shipping per line, flash sale
 *      discount owned by its source);
 *   2. the shipping charge comes from Shipping\ShippingQuoteService on the catalog lines
 *      only (own products weigh 0 and never block a destination);
 *   3. the coupon value is computed on the items_grand_total of the whole cart; a mixed
 *      cart is split into the catalog order (shipping) and the own companion order
 *      (shipping 0) and the coupon is shared by items_grand_total (a free-shipping coupon
 *      stays on the order that pays the shipping);
 *   4. each order gets its platform / vendor split (OrderPricing::order()).
 */
class OrderComposer
{
    public function __construct(private readonly ShippingQuoteService $shipping) {}

    /**
     * @param  list<OrderLineInput>  $lines
     */
    public function compose(array $lines, ?int $countryId, ?AppliedCoupon $coupon = null, ?int $cityId = null, ?int $zoneId = null): ComposedOrder
    {
        $quote = $this->shipping->quote(new ShippingQuoteRequest($countryId, self::quoteLines($lines), $cityId, $zoneId));

        return self::composeWithQuote($lines, $quote, $coupon);
    }

    /**
     * Pure variant for a known shipping quote (tests, previews).
     *
     * @param  list<OrderLineInput>  $lines
     */
    public static function composeWithQuote(array $lines, ShippingQuote $quote, ?AppliedCoupon $coupon = null): ComposedOrder
    {
        $catalog = [];
        $own = [];

        foreach ($lines as $line) {
            $priced = OrderPricing::item($line);
            $line->own ? $own[] = $priced : $catalog[] = $priced;
        }

        if ($catalog === [] && $own === []) {
            return new ComposedOrder(null, null, $quote, $coupon);
        }

        $shipping = $quote->available && $catalog !== [] ? $quote->amount : Money::zero();
        $catalogItems = self::itemsTotal($catalog);
        $ownItems = self::itemsTotal($own);
        $allItems = $catalogItems->add($ownItems);
        $couponTotal = $coupon?->discountFor($allItems, $shipping) ?? Money::zero();
        $source = $coupon?->source;

        if ($catalog !== [] && $own !== []) {
            [$catalogCoupon, $ownCoupon] = $coupon?->type === CouponType::FreeShipping
                ? [$couponTotal, Money::zero()]
                : OrderPricing::splitCoupon($couponTotal, $catalogItems, $allItems);

            return new ComposedOrder(
                OrderPricing::order($catalog, $shipping, $catalogCoupon, $source),
                OrderPricing::order($own, Money::zero(), $ownCoupon, $source),
                $quote,
                $coupon,
            );
        }

        return new ComposedOrder(OrderPricing::order([...$catalog, ...$own], $shipping, $couponTotal, $source), null, $quote, $coupon);
    }

    /**
     * Shipping quote lines: unit weight × qty, own and digital products not shippable.
     *
     * @param  list<OrderLineInput>  $lines
     * @return list<QuoteLine>
     */
    public static function quoteLines(array $lines): array
    {
        return array_map(static fn (OrderLineInput $line): QuoteLine => new QuoteLine($line->key, $line->own ? 0 : $line->weightGrams, $line->quantity, ! $line->own && $line->shippable), $lines);
    }

    /**
     * @param  list<PricedLine>  $lines
     */
    private static function itemsTotal(array $lines): Money
    {
        return Money::zero()->add(...array_map(static fn (PricedLine $line): Money => $line->grandTotal, $lines));
    }
}
