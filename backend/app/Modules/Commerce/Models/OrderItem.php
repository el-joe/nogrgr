<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Pricing\PricedLine;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One order line: product snapshot (name, variant, sku, image) and the PricedLine money
 * snapshot (ORDER_FORMULAS.md §1/§4), USD minor units.
 *
 * @property int $id
 * @property int $order_id
 * @property string $line_key
 * @property int $store_product_id
 * @property StoreProductSource $product_source
 * @property int $product_id
 * @property int $variant_key
 * @property string $name
 * @property string|null $variant_label
 * @property string|null $sku
 * @property string|null $image_url
 * @property int $quantity
 * @property int $cost
 * @property int $profit_amount
 * @property int $price
 * @property int $sub_total
 * @property int $fixed_shipping
 * @property int $discount
 * @property DiscountSource|null $discount_source
 * @property int|null $flash_sale_id
 * @property int $tax
 * @property int $shipping_fee
 * @property int $grand_total
 * @property int $weight_grams
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 */
class OrderItem extends Model
{
    /**
     * Explicit storage format: admin pages read store orders after leaving the store's
     * tenant context, where the "tenant" connection (default date format) is gone.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id', 'line_key', 'store_product_id', 'product_source', 'product_id', 'variant_key', 'name', 'variant_label',
        'sku', 'image_url', 'quantity', 'cost', 'profit_amount', 'price', 'sub_total', 'fixed_shipping', 'discount',
        'discount_source', 'flash_sale_id', 'tax', 'shipping_fee', 'grand_total', 'weight_grams',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_source' => StoreProductSource::class,
            'discount_source' => DiscountSource::class,
            'order_id' => 'integer',
            'store_product_id' => 'integer',
            'product_id' => 'integer',
            'variant_key' => 'integer',
            'quantity' => 'integer',
            'cost' => 'integer',
            'profit_amount' => 'integer',
            'price' => 'integer',
            'sub_total' => 'integer',
            'fixed_shipping' => 'integer',
            'discount' => 'integer',
            'flash_sale_id' => 'integer',
            'tax' => 'integer',
            'shipping_fee' => 'integer',
            'grand_total' => 'integer',
            'weight_grams' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The line's money snapshot as the PricedLine it was stored from (shares per
     * ORDER_FORMULAS §2 come from PricedLine, never recomputed elsewhere).
     */
    public function pricedLine(): PricedLine
    {
        $money = static fn (?int $minor): Money => Money::ofMinor((int) $minor);

        return new PricedLine(
            key: (string) $this->line_key,
            own: $this->product_source === StoreProductSource::Own,
            quantity: (int) $this->quantity,
            cost: $money($this->cost),
            profitAmount: $money($this->profit_amount),
            price: $money($this->price),
            subTotal: $money($this->sub_total),
            fixedShipping: $money($this->fixed_shipping),
            discount: $money($this->discount),
            discountSource: $this->discount_source,
            tax: $money($this->tax),
            shippingFee: $money($this->shipping_fee),
            grandTotal: $money($this->grand_total),
            weightGrams: (int) $this->weight_grams,
        );
    }
}
