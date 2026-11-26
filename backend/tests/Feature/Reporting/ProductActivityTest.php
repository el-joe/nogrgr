<?php

use App\Modules\Commerce\Events\CartItemAdded;
use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Reporting\Models\ProductDailyFact;
use App\Modules\Storefront\Events\ProductViewed;
use Illuminate\Support\Str;

require_once __DIR__.'/ReportingHelpers.php';

/*
| Product performance facts (ADM-070j): views, add-to-cart and paid orders per store
| product per day, a replayed OrderPaid counted once, and the Products report tab.
*/

beforeEach(function (): void {
    $this->megz = reportTenant('Megz Store');
});

function paidOrderWith(array $lines): Order
{
    $order = new Order(['uuid' => (string) Str::uuid()]);
    $order->paid_at = now();
    $order->setRelation('items', collect(array_map(static fn (array $line): OrderItem => new OrderItem($line), $lines)));

    return $order;
}

it('records views, cart adds and paid orders per product and day', function (): void {
    $tenantId = (int) $this->megz->id;

    ProductViewed::dispatch($tenantId, 11, 'Smart Watch Series 4');
    ProductViewed::dispatch($tenantId, 11, 'Smart Watch Series 4');
    ProductViewed::dispatch($tenantId, 12, 'Linen Summer Dress');
    CartItemAdded::dispatch($tenantId, 11, 2);

    $order = paidOrderWith([
        ['store_product_id' => 11, 'name' => 'Smart Watch Series 4', 'quantity' => 2, 'grand_total' => 39800],
        ['store_product_id' => 11, 'name' => 'Smart Watch Series 4', 'quantity' => 1, 'grand_total' => 19900],
    ]);
    OrderPaid::dispatch($order, $tenantId);
    OrderPaid::dispatch($order, $tenantId);

    $watch = ProductDailyFact::withoutTenantScope()->where('store_product_id', 11)->sole();

    expect($watch->views)->toBe(2)
        ->and($watch->cart_adds)->toBe(1)
        ->and($watch->orders)->toBe(1)
        ->and($watch->units)->toBe(3)
        ->and($watch->revenue)->toBe(59700)
        ->and($watch->product_name)->toBe('Smart Watch Series 4')
        ->and(ProductDailyFact::withoutTenantScope()->where('store_product_id', 12)->sole()->views)->toBe(1);
});

it('shows product performance with the conversion rate', function (): void {
    $this->actingAs(reportAdmin(['reports.view']), 'admin');
    $tenantId = (int) $this->megz->id;

    foreach (range(1, 4) as $view) {
        ProductViewed::dispatch($tenantId, 21, 'Wireless Earbuds Pro');
    }
    OrderPaid::dispatch(paidOrderWith([['store_product_id' => 21, 'name' => 'Wireless Earbuds Pro', 'quantity' => 1, 'grand_total' => 5000]]), $tenantId);

    $this->get(route('admin.reports.show', 'products'))->assertOk()->assertSee('25%');

    $rows = $this->getJson(route('admin.reports.data', ['tab' => 'products', 'draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1)
        ->json('data');

    expect(strip_tags($rows[0]['c_product_name']))->toContain('Wireless Earbuds Pro')
        ->and(strip_tags($rows[0]['c_product_name']))->toContain('Megz Store')
        ->and(strip_tags($rows[0]['c_views']))->toBe('4')
        ->and(strip_tags($rows[0]['c_conversion']))->toBe('25%');
});
