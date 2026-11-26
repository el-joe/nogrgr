<?php

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Notifications\OrderCustomerNotification;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Models\BranchStock;
use App\Modules\Platform\Services\StoreSettingsService;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/FulfilmentHelpers.php';

/*
| MAIL-015: the order-delivered e-mail carries a "Write a review" button that opens the
| review modal of the storefront order page (storefront.orders.show?review=1), en + ar.
*/

beforeEach(function (): void {
    Notification::fake();
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
    $this->order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 1], 'delivered-mail-1', 'central');

    // Delivered from the admin shipping queue (a central request): links must still point to the store.
    $fact = factId($this->order);
    $branch = Branch::factory()->create(['is_active' => true]);
    BranchStock::query()->create(['branch_id' => $branch->id, 'product_id' => $this->shop->central->id, 'variant_key' => 0, 'on_hand' => 10, 'reserved' => 0]);
    $this->actingAs(commerceAdmin(), 'admin');
    $this->putJson($this->centralUrl("/admin/shipping-queue/{$fact}/status"), ['status' => 'in_transit', 'tracking_number' => 'AWB9', 'branch_id' => $branch->id])->assertOk();
    $this->putJson($this->centralUrl("/admin/shipping-queue/{$fact}/status"), ['status' => 'delivered'])->assertOk();
    expect(orderIn($this->shop, $this->order)->status)->toBe(OrderStatus::Delivered);

    $this->notification = null;
    Notification::assertSentTo($this->customer, OrderCustomerNotification::class, function (OrderCustomerNotification $notification): bool {
        if ($notification->eventName === 'delivered') {
            $this->notification = $notification;
        }

        return $notification->eventName === 'delivered';
    });
    $this->expectedUrl = StoreSettingsService::storeUrl($this->shop->tenant).'/orders/'.$this->order->uuid.'?review=1';
});

it('links the delivered e-mail to the review modal of the storefront order page', function (): void {
    expect($this->notification->variables['order.review_url'])->toBe($this->expectedUrl)
        ->and($this->notification->variables['order.url'])->toBe(StoreSettingsService::storeUrl($this->shop->tenant).'/orders/'.$this->order->uuid);

    $mail = $this->notification->toMail($this->customer->forceFill(['locale' => 'en']));

    expect($mail->actionText)->toBe('Write a review')
        ->and($mail->actionUrl)->toBe($this->expectedUrl)
        ->and($mail->introLines)->toContain(__('commerce::notifications.customer.review_prompt', [], 'en'));
});

it('translates the review button in Arabic', function (): void {
    $mail = $this->notification->toMail($this->customer->forceFill(['locale' => 'ar']));

    expect($mail->actionText)->toBe('اكتب تقييمًا')
        ->and($mail->actionUrl)->toBe($this->expectedUrl);
});

it('keeps the view-order button on other order e-mails', function (): void {
    Notification::assertSentTo($this->customer, OrderCustomerNotification::class, function (OrderCustomerNotification $notification): bool {
        if ($notification->eventName !== 'shipped') {
            return false;
        }

        $mail = $notification->toMail($this->customer->forceFill(['locale' => 'en']));

        return ! array_key_exists('order.review_url', $notification->variables) && $mail->actionText === 'View order';
    });
});
