<?php

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Models\OrderItem;

/*
| OrderItem::pricedLine() rebuilds the stored line snapshot so the store's product profit
| (VEN-045d) comes from PricedLine::vendorShare (ORDER_FORMULAS §2), never re-derived.
*/

function snapshotItem(array $overrides = []): OrderItem
{
    return (new OrderItem)->forceFill(array_merge([
        'line_key' => 'p1-0', 'store_product_id' => 1, 'product_source' => StoreProductSource::Central, 'product_id' => 7, 'variant_key' => 0,
        'name' => 'Linen Dress', 'quantity' => 3, 'cost' => 1000, 'profit_amount' => 400, 'price' => 1400, 'sub_total' => 4200,
        'fixed_shipping' => 150, 'discount' => 0, 'discount_source' => null, 'tax' => 0, 'shipping_fee' => 0, 'grand_total' => 4350, 'weight_grams' => 300,
    ], $overrides));
}

it('gives the vendor profit × quantity without a vendor flash discount', function (): void {
    $line = snapshotItem()->pricedLine();

    expect($line->vendorShare()->minor)->toBe(1200)
        ->and($line->platformShare()->minor)->toBe(3150)
        ->and($line->vendorShare()->add($line->platformShare())->minor)->toBe(4350);
});

it('takes a vendor flash discount off the vendor share only', function (): void {
    $line = snapshotItem(['discount' => 435, 'discount_source' => DiscountSource::Vendor, 'grand_total' => 3915])->pricedLine();

    expect($line->vendorShare()->minor)->toBe(765)
        ->and($line->platformShare()->minor)->toBe(3150);
});

it('takes a central flash discount off the platform share only', function (): void {
    $line = snapshotItem(['discount' => 435, 'discount_source' => DiscountSource::Central, 'grand_total' => 3915])->pricedLine();

    expect($line->vendorShare()->minor)->toBe(1200)
        ->and($line->platformShare()->minor)->toBe(2715)
        ->and($line->own)->toBeFalse();
});
