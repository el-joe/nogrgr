<?php

namespace App\Modules\Commerce\Pricing;

use App\Modules\Commerce\Enums\DiscountSource;
use App\Shared\Money\Money;

/**
 * One priced order line (the `order_items` money snapshot, ORDER_FORMULAS.md §1/§4):
 *
 *   price            = cost + profit_amount
 *   sub_total        = price × qty
 *   discount         = money((sub_total + fixed_shipping) × flash_sale_pct / 100)
 *   grand_total      = sub_total + fixed_shipping − discount + tax + shipping_fee (0)
 */
final readonly class PricedLine
{
    public function __construct(
        public string $key,
        public bool $own,
        public int $quantity,
        public Money $cost,
        public Money $profitAmount,
        public Money $price,
        public Money $subTotal,
        public Money $fixedShipping,
        public Money $discount,
        public ?DiscountSource $discountSource,
        public Money $tax,
        public Money $shippingFee,
        public Money $grandTotal,
        public int $weightGrams,
    ) {}

    /**
     * Customer-facing "items subtotal" of the line: sub_total + fixed_shipping (fixed
     * shipping is never shown as its own row).
     */
    public function displaySubtotal(): Money
    {
        return $this->subTotal->add($this->fixedShipping);
    }

    /**
     * Platform share of the line before order-level amounts: cost × qty + fixed − central discount.
     */
    public function platformShare(): Money
    {
        $share = $this->cost->multiply($this->quantity)->add($this->fixedShipping);

        return $this->discountSource === DiscountSource::Central ? $share->subtract($this->discount) : $share;
    }

    /**
     * Vendor share of the line: profit_amount × qty − vendor discount.
     */
    public function vendorShare(): Money
    {
        $share = $this->profitAmount->multiply($this->quantity);

        return $this->discountSource === DiscountSource::Vendor ? $share->subtract($this->discount) : $share;
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'own' => $this->own,
            'qty' => $this->quantity,
            'cost' => $this->cost->minor,
            'profit_amount' => $this->profitAmount->minor,
            'price' => $this->price->minor,
            'sub_total' => $this->subTotal->minor,
            'fixed_shipping' => $this->fixedShipping->minor,
            'discount' => $this->discount->minor,
            'discount_source' => $this->discountSource?->value,
            'tax' => $this->tax->minor,
            'shipping_fee' => $this->shippingFee->minor,
            'grand_total' => $this->grandTotal->minor,
            'weight' => $this->weightGrams,
        ];
    }
}
