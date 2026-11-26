<?php

use App\Modules\Commerce\Enums\OrderKind;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentSource;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Events\CartItemAdded;
use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Notifications\OrderAlertNotification;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Reporting\Listeners\RecordProductActivity;
use App\Modules\Reporting\Models\ProductDailyFact;
use App\Modules\Storefront\Events\ProductViewed;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

require_once __DIR__.'/ReportingHelpers.php';

/*
| Product performance facts (ADM-070j): views, add-to-cart and paid orders per store
| product per day, a replayed OrderPaid counted once, and the Products report tab.
| OrderPaid is dispatched inside the store's tenancy, as OrderLifecycleService does in
| production (its other listeners, e.g. the store owner alert, read the tenant database).
*/

beforeEach(function (): void {
    $this->megz = $this->createTenant('pa-'.Str::lower(Str::random(8)), ['name' => 'Megz Store']);
});

/**
 * Dispatches OrderPaid in the store's tenant context (like OrderLifecycleService::markPaid).
 * The replay check calls the listener directly: the tests' array cache is rebuilt whenever
 * tenancy is (re)initialised, so a replay mark would not survive between dispatches.
 */
function paidInStore(Tenant $tenant, Order $order): void
{
    $tenant->run(fn () => OrderPaid::dispatch($order, (int) $tenant->id));
}

function paidOrderWith(array $lines): Order
{
    $total = (int) array_sum(array_column($lines, 'grand_total'));
    $order = new Order;
    $order->forceFill([
        'uuid' => (string) Str::uuid(), 'number' => 'NG-'.Str::upper(Str::random(8)), 'kind' => OrderKind::Catalog, 'status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid, 'payment_source' => PaymentSource::Tenant, 'currency' => 'USD', 'customer_name' => 'Ahmed Ali',
        'grand_total' => $total, 'platform_profit' => intdiv($total, 5), 'vendor_profit' => $total - intdiv($total, 5), 'paid_at' => now(),
    ]);
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
        ['store_product_id' => 11, 'name' => 'Smart Watch Series 4', 'quantity' => 2, 'grand_total' => 39800, 'profit_amount' => 5000],
        ['store_product_id' => 11, 'name' => 'Smart Watch Series 4', 'quantity' => 1, 'grand_total' => 19900, 'profit_amount' => 4000, 'discount' => 1000, 'discount_source' => 'vendor'],
    ]);
    $listener = app(RecordProductActivity::class);
    $listener->handle(new OrderPaid($order, $tenantId));
    $listener->handle(new OrderPaid($order, $tenantId));

    $watch = ProductDailyFact::withoutTenantScope()->where('store_product_id', 11)->sole();

    expect($watch->views)->toBe(2)
        ->and($watch->cart_adds)->toBe(1)
        ->and($watch->orders)->toBe(1)
        ->and($watch->units)->toBe(3)
        ->and($watch->revenue)->toBe(59700)
        ->and($watch->profit)->toBe(13000)
        ->and($watch->product_name)->toBe('Smart Watch Series 4')
        ->and(ProductDailyFact::withoutTenantScope()->where('store_product_id', 12)->sole()->views)->toBe(1);
});

it('shows product performance with the conversion rate', function (): void {
    $this->actingAs(reportAdmin(['reports.view']), 'admin');
    $tenantId = (int) $this->megz->id;

    foreach (range(1, 4) as $view) {
        ProductViewed::dispatch($tenantId, 21, 'Wireless Earbuds Pro');
    }
    paidInStore($this->megz, paidOrderWith([['store_product_id' => 21, 'name' => 'Wireless Earbuds Pro', 'quantity' => 1, 'grand_total' => 5000]]));

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

it('alerts the store owners of a paid order from the store tenancy while recording the facts', function (): void {
    Notification::fake();
    $owner = $this->createVendorOwner($this->megz, ['email' => 'own-'.Str::lower(Str::random(6)).'@megz.test']);

    paidInStore($this->megz, paidOrderWith([['store_product_id' => 41, 'name' => 'Linen Summer Dress', 'quantity' => 1, 'grand_total' => 4500]]));

    Notification::assertSentTo($owner, OrderAlertNotification::class, fn (OrderAlertNotification $notification): bool => $notification->forStore);
    expect(ProductDailyFact::withoutTenantScope()->where('tenant_id', $this->megz->id)->where('store_product_id', 41)->sole()->revenue)->toBe(4500);
});
