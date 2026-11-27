<?php

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Services\Vendor\OwnProductService;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Inventory\Enums\StockAlertEvent;
use App\Modules\Inventory\Models\StockAlertSubscription;
use App\Modules\Inventory\Notifications\BackInStockNotification;
use App\Modules\Inventory\Notifications\StockAlertNotification;
use App\Modules\Inventory\Services\Alerts\StockAlertService;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Shared\Money\Money;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

require_once dirname(__DIR__).'/Commerce/StoreApiHelpers.php';

/*
| Stock alerts (decision #124): back-in-stock subscriptions (Store API, guest e-mail or
| signed-in customer, sent once when the stock returns) and the team's low / out of
| stock alerts (product minimum → store threshold), plus the VEN-087 threshold card.
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('stockalerts');
    $this->owner = $this->createVendorOwner($this->tenant);
    [$this->product, $this->customer] = $this->tenant->run(fn (): array => [
        StoreProduct::factory()->create(['source' => StoreProductSource::Own, 'price' => Money::of('20.00'), 'stock' => 0, 'manage_stock' => true, 'min_stock' => 0, 'is_active' => true]),
        Customer::factory()->create(['name' => 'Sara Ahmed', 'email' => 'sara@example.test']),
    ]);
    $this->headers = shopHeaders($this->tenant);
    $this->alertsUrl = fn (?int $product = null): string => storeApi('products/'.($product ?? $this->product->id).'/stock-alerts');
});

it('lets a guest subscribe to an out-of-stock product with an e-mail', function (): void {
    $this->postJson(($this->alertsUrl)(), [], $this->headers)->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson(($this->alertsUrl)(), ['email' => 'Guest@Example.test'], $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.subscribed', true)
        ->assertJsonPath('data.email', 'guest@example.test');

    $this->tenant->run(fn () => expect(StockAlertSubscription::query()->where('email', 'guest@example.test')->whereNull('notified_at')->count())->toBe(1));
});

it('refuses subscriptions for in-stock or unknown products', function (): void {
    $this->tenant->run(fn () => $this->product->forceFill(['stock' => 4])->save());

    $this->postJson(($this->alertsUrl)(), ['email' => 'guest@example.test'], $this->headers)->assertUnprocessable()->assertJsonValidationErrors('product');
    $this->postJson(($this->alertsUrl)(999999), ['email' => 'guest@example.test'], $this->headers)->assertUnprocessable()->assertJsonValidationErrors('product');
});

it('subscribes a signed-in customer with the account e-mail and reports the status', function (): void {
    shopActingAs($this->customer);

    $this->getJson(($this->alertsUrl)(), $this->headers)->assertOk()->assertJsonPath('data.subscribed', false);
    $this->postJson(($this->alertsUrl)(), [], $this->headers)->assertCreated()->assertJsonPath('data.email', 'sara@example.test');
    $this->getJson(($this->alertsUrl)(), $this->headers)->assertOk()->assertJsonPath('data.subscribed', true);
});

it('sends the back-in-stock notice once when the stock returns', function (): void {
    Notification::fake();
    $this->tenant->run(function (): void {
        StockAlertSubscription::factory()->create(['store_product_id' => $this->product->id, 'customer_id' => $this->customer->id, 'email' => 'sara@example.test']);
        StockAlertSubscription::factory()->create(['store_product_id' => $this->product->id, 'email' => 'guest@example.test']);
        StockAlertSubscription::factory()->notified()->create(['store_product_id' => $this->product->id, 'email' => 'old@example.test']);

        app(OwnProductService::class)->updateStock($this->product->fresh(), 6);
        app(OwnProductService::class)->updateStock($this->product->fresh(), 0);
        app(OwnProductService::class)->updateStock($this->product->fresh(), 3);
    });

    Notification::assertSentTo($this->customer, BackInStockNotification::class, fn (BackInStockNotification $notice, array $channels): bool => $notice->storeProductId === $this->product->id && in_array('database', $channels, true));
    Notification::assertSentTimes(BackInStockNotification::class, 2);
    Notification::assertSentTo(new AnonymousNotifiable, BackInStockNotification::class, fn ($notice, $channels, $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'guest@example.test');
    $this->tenant->run(fn () => expect(StockAlertSubscription::query()->whereNull('notified_at')->count())->toBe(0));
});

it('alerts the team at the store threshold and when the stock runs out', function (): void {
    Notification::fake();
    $this->tenant->run(function (): void {
        $this->product->forceFill(['stock' => 20])->save();
        app(StockAlertService::class)->setStoreThreshold(5);

        app(OwnProductService::class)->updateStock($this->product->fresh(), 8);   // above the threshold: nothing
        app(OwnProductService::class)->updateStock($this->product->fresh(), 4);   // crosses 5 → low stock
        app(OwnProductService::class)->updateStock($this->product->fresh(), 2);   // already low: nothing
        app(OwnProductService::class)->updateStock($this->product->fresh(), 0);   // out of stock
    });

    Notification::assertSentTo($this->owner, StockAlertNotification::class, fn (StockAlertNotification $alert): bool => $alert->alert === StockAlertEvent::LowStock && $alert->parameters['stock'] === 4 && $alert->parameters['threshold'] === 5);
    Notification::assertSentTo($this->owner, StockAlertNotification::class, fn (StockAlertNotification $alert): bool => $alert->alert === StockAlertEvent::OutOfStock);
    Notification::assertSentTimes(StockAlertNotification::class, 2);
});

it('prefers the product minimum stock over the store threshold', function (): void {
    $this->tenant->run(function (): void {
        $service = app(StockAlertService::class);
        $service->setStoreThreshold(5);
        $view = app(StoreCatalogReader::class)->view($this->product->forceFill(['min_stock' => 12]));

        expect($service->threshold($view))->toBe(12);
    });
});

it('maps the stock alert to the vendor.low_stock template', function (): void {
    $alert = new StockAlertNotification(StockAlertEvent::OutOfStock, ['product' => 'Shirt', 'stock' => 0, 'threshold' => 5, 'store' => 'Shop']);

    expect($alert->mailTemplate($this->owner))->toBe(EmailTemplateEvent::VendorLowStock)
        ->and(StockAlertNotification::mailTemplates())->toContain(EmailTemplateEvent::VendorLowStock);
});

it('saves the store-wide low-stock threshold for members who manage products', function (): void {
    $url = $this->tenantUrl($this->tenant, 'admin/settings/notifications/stock-threshold');

    $this->actingAsVendor($this->owner, $this->tenant)->putJson($url, ['low_stock_threshold' => -1])->assertUnprocessable()->assertJsonValidationErrors('low_stock_threshold');
    $this->actingAsVendor($this->owner, $this->tenant)->putJson($url, ['low_stock_threshold' => 9])->assertOk()->assertJsonPath('data.low_stock_threshold', 9);
    $this->tenant->run(fn () => expect(app(StockAlertService::class)->storeThreshold())->toBe(9));

    $staff = $this->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view', 'notifications.view']);

        return $staff;
    });

    $this->actingAsVendor($staff, $this->tenant)->putJson($url, ['low_stock_threshold' => 3])->assertForbidden();
    $this->actingAsVendor($staff, $this->tenant)->get($this->tenantUrl($this->tenant, 'admin/settings/notifications'))
        ->assertOk()
        ->assertDontSee(__('inventory::alerts.threshold.title'));
    $this->actingAsVendor($this->owner, $this->tenant)->get($this->tenantUrl($this->tenant, 'admin/settings/notifications'))
        ->assertOk()
        ->assertSee(__('inventory::alerts.threshold.title'));
});
