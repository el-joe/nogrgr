<?php

use App\Modules\AfterSales\DTOs\Actor;
use App\Modules\AfterSales\DTOs\ReturnActionData;
use App\Modules\AfterSales\Enums\ReturnAction;
use App\Modules\AfterSales\Enums\ReturnEventType;
use App\Modules\AfterSales\Enums\ReturnMediaKind;
use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Models\ReturnPolicy;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Services\ReturnRequestService;
use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Notifications\OrderAlertNotification;
use App\Modules\Commerce\Notifications\OrderCustomerNotification;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Models\BranchStock;
use App\Modules\Localization\Models\Currency;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';

/*
| SF-ECO-030/031/032, APP-016/017/018: customer returns through the Store API. Golden
| order: 2 × central (line grand 58.80), shipping 5.00, total 63.80, delivered by the
| platform; one unit returned = 29.40 (RefundCalculator).
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
    $this->order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2]);
    $this->item = $this->order->items->first();
    $fact = factId($this->order);
    $branch = Branch::factory()->create(['is_active' => true]);
    BranchStock::query()->create(['branch_id' => $branch->id, 'product_id' => $this->shop->central->id, 'variant_key' => 0, 'on_hand' => 10, 'reserved' => 0]);
    $this->deliver = function () use ($fact, $branch): void {
        tenancy()->end();
        $this->actingAs(commerceAdmin(), 'admin');
        $this->putJson($this->centralUrl("/admin/shipping-queue/{$fact}/status"), ['status' => 'in_transit', 'tracking_number' => 'AWB1', 'branch_id' => $branch->id])->assertOk();
        $this->putJson($this->centralUrl("/admin/shipping-queue/{$fact}/status"), ['status' => 'delivered'])->assertOk();
        shopActingAs($this->customer);
    };
    ReturnPolicy::query()->update(['require_video' => false, 'window_days' => 14, 'non_returnable_category_ids' => null]);
    $this->headers = shopHeaders($this->shop->tenant);
    $this->payload = fn (array $overrides = []): array => array_replace([
        'reason_code' => 'changed_mind',
        'description' => 'Ordered the wrong colour.',
        'type' => 'return',
        'items' => [['order_item_id' => $this->item->id, 'quantity' => 1]],
    ], $overrides);
    $this->open = function (array $overrides = []): ReturnRequest {
        $this->post(storeApi("orders/{$this->order->uuid}/returns"), ($this->payload)($overrides), $this->headers)->assertCreated();

        return ReturnRequest::withoutTenantScope()->latest('id')->firstOrFail();
    };
    shopActingAs($this->customer);
});

it('requires a signed-in customer', function (): void {
    $this->app['auth']->forgetGuards();

    $this->getJson(storeApi('returns'), $this->headers)->assertUnauthorized()->assertJsonPath('success', false);
    $this->getJson(storeApi("orders/{$this->order->uuid}/returns/options"), $this->headers)->assertUnauthorized();
    $this->postJson(storeApi("orders/{$this->order->uuid}/returns"), [], $this->headers)->assertUnauthorized();
    $this->postJson(storeApi('returns/1/messages'), [], $this->headers)->assertUnauthorized();
    $this->postJson(storeApi('returns/1/withdraw'), [], $this->headers)->assertUnauthorized();
});

it('lists the return options and why an order cannot be returned yet', function (): void {
    $this->getJson(storeApi("orders/{$this->order->uuid}/returns/options"), $this->headers)
        ->assertOk()
        ->assertJsonPath('data.can_return', false)
        ->assertJsonPath('data.blocked_reason', 'order_not_returnable');

    ($this->deliver)();

    $this->getJson(storeApi("orders/{$this->order->uuid}/returns/options"), $this->headers)
        ->assertOk()
        ->assertJsonPath('data.can_return', true)
        ->assertJsonPath('data.lines.0.order_item_id', $this->item->id)
        ->assertJsonPath('data.lines.0.available', 2)
        ->assertJsonPath('data.lines.0.returnable', true)
        ->assertJsonPath('data.policy.window_days', 14)
        ->assertJsonPath('data.policy.uploads.video.max_size_kb', 51200)
        ->assertJsonFragment(['code' => 'defective', 'requires_video' => true])
        ->assertJsonFragment(['code' => 'changed_mind', 'requires_video' => false]);
});

it('validates the return request', function (): void {
    ($this->deliver)();
    $url = storeApi("orders/{$this->order->uuid}/returns");

    $this->postJson($url, ($this->payload)(['reason_code' => null, 'description' => 'short']), $this->headers)
        ->assertJsonValidationErrors(['reason_code', 'description']);
    $this->post($url, ($this->payload)(['photos' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]]), $this->headers)
        ->assertJsonValidationErrors('photos.0');
    $this->post($url, ($this->payload)(['video' => UploadedFile::fake()->create('clip.mp4', 52000, 'video/mp4')]), $this->headers)
        ->assertJsonValidationErrors('video');
    $this->postJson($url, ($this->payload)(['items' => [['order_item_id' => $this->item->id, 'quantity' => 3]]]), $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'quantity_exceeds');
    $this->postJson($url, ($this->payload)(['items' => [['order_item_id' => 999999, 'quantity' => 1]]]), $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'line_not_returnable');

    expect(ReturnRequest::withoutTenantScope()->count())->toBe(0);
});

it('refuses returns after the return window', function (): void {
    ($this->deliver)();
    $this->shop->tenant->run(fn () => Order::query()->whereKey($this->order->id)->update(['delivered_at' => now()->subDays(15)]));

    $this->postJson(storeApi("orders/{$this->order->uuid}/returns"), ($this->payload)(), $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'return_window_expired');
    $this->getJson(storeApi("orders/{$this->order->uuid}/returns/options"), $this->headers)
        ->assertJsonPath('data.can_return', false)
        ->assertJsonPath('data.blocked_reason', 'return_window_expired');
});

it('refuses items of a non-returnable category and its sub-categories', function (): void {
    ($this->deliver)();
    $parent = Category::factory()->create();
    $child = Category::factory()->childOf($parent)->create();
    $this->shop->central->categories()->attach($child->id);
    ReturnPolicy::query()->update(['non_returnable_category_ids' => json_encode([$parent->id])]);

    $this->postJson(storeApi("orders/{$this->order->uuid}/returns"), ($this->payload)(), $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'category_not_returnable');
    $this->getJson(storeApi("orders/{$this->order->uuid}/returns/options"), $this->headers)
        ->assertJsonPath('data.lines.0.returnable', false)
        ->assertJsonPath('data.blocked_reason', 'nothing_returnable');
});

it('requires a video when the policy or the reason asks for one', function (): void {
    ($this->deliver)();
    $url = storeApi("orders/{$this->order->uuid}/returns");

    $this->postJson($url, ($this->payload)(['reason_code' => 'defective']), $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'video_required');

    ReturnPolicy::query()->update(['require_video' => true]);
    $this->postJson($url, ($this->payload)(), $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'video_required');

    $this->post($url, ($this->payload)(['reason_code' => 'defective', 'video' => UploadedFile::fake()->create('clip.mp4', 2000, 'video/mp4')]), $this->headers)
        ->assertCreated();

    expect(ReturnRequest::withoutTenantScope()->sole()->media()->sole()->kind)->toBe(ReturnMediaKind::Video);
});

it('opens a return with evidence and notifies the customer, store and admins', function (): void {
    ($this->deliver)();

    $response = $this->post(storeApi("orders/{$this->order->uuid}/returns"), ($this->payload)([
        'photos' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.png')],
        'video' => UploadedFile::fake()->create('clip.mov', 3000, 'video/quicktime'),
    ]), $this->headers)->assertCreated()->assertJsonPath('success', true);

    $return = ReturnRequest::withoutTenantScope()->sole();

    expect($return->tenant_id)->toBe($this->shop->tenant->id)
        ->and($return->customer_id)->toBe($this->customer->id)
        ->and($return->status)->toBe(ReturnStatus::Pending)
        ->and($return->quantity)->toBe(1)
        ->and($return->refund_amount)->toBe(2940)
        ->and($return->created_by_type)->toBe(ActorType::Customer)
        ->and($return->media()->count())->toBe(3);

    $response->assertJsonPath('data.returns.0.reference', $return->reference)
        ->assertJsonPath('data.returns.0.amounts.refund.amount', 2940)
        ->assertJsonCount(3, 'data.returns.0.media');
    $return->media->each(fn ($media) => Storage::disk('central')->assertExists($media->path));

    Notification::assertSentTo($this->customer, OrderCustomerNotification::class, fn (OrderCustomerNotification $n): bool => $n->eventName === 'return_pending');
    Notification::assertSentTo(app(AdminRepositoryContract::class)->activeWithPermission('returns.view'), OrderAlertNotification::class, fn (OrderAlertNotification $n): bool => $n->eventName === 'admin_return_opened');

    $this->getJson(storeApi("orders/{$this->order->uuid}/returns/options"), $this->headers)->assertJsonPath('data.lines.0.available', 1);
});

it('lists and shows own returns with signed media and only customer-visible events', function (): void {
    ($this->deliver)();
    $return = ($this->open)(['photos' => [UploadedFile::fake()->image('front.jpg')]]);
    app(ReturnRequestService::class)->addNote($return, 'Internal: customer is a reseller.', false, Actor::system());

    $this->getJson(storeApi('returns'), $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data.returns')
        ->assertJsonPath('data.returns.0.id', $return->id)
        ->assertJsonPath('data.meta.total', 1);
    $this->getJson(storeApi("orders/{$this->order->uuid}/returns"), $this->headers)->assertJsonCount(1, 'data.returns');

    $show = $this->getJson(storeApi("returns/{$return->id}"), $this->headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.can_withdraw', true)
        ->assertJsonPath('data.reason.name', 'Changed mind')
        ->assertJsonCount(1, 'data.timeline')
        ->assertJsonMissing(['message' => 'Internal: customer is a reseller.']);

    $url = $show->json('data.media.0.url');
    expect($url)->toContain('signature=')->toContain('tenant='.$this->shop->tenant->id);
    $this->get($url, $this->headers)->assertOk();
    $this->get(strtok($url, '?'), $this->headers)->assertForbidden();

    // RQA-APP-016: the link names its store itself (Image.network / in-app browser send no X-Tenant).
    $this->app['auth']->forgetGuards();
    $this->get($url)->assertOk();
    $other = shopSetup('evidence-other');
    tenancy()->end();
    $this->get(str_replace('tenant='.$this->shop->tenant->id, 'tenant='.$other->tenant->id, $url), shopHeaders($other->tenant))->assertForbidden();
    $legacy = URL::temporarySignedRoute('store-api.v1.returns.media', now()->addMinutes(30), ['return' => $return->id, 'media' => $return->media->first()->id], absolute: false);
    $this->get($legacy, $this->headers)->assertForbidden();
    $this->travel(31)->minutes();
    $this->get($url, $this->headers)->assertForbidden();
});

it('hides returns of other customers and other stores', function (): void {
    ($this->deliver)();
    $return = ($this->open)();

    shopActingAs(shopCustomer($this->shop));
    $this->getJson(storeApi("returns/{$return->id}"), $this->headers)->assertNotFound();
    $this->getJson(storeApi("orders/{$this->order->uuid}/returns/options"), $this->headers)->assertNotFound();
    $this->postJson(storeApi("returns/{$return->id}/withdraw"), [], $this->headers)->assertNotFound();
    $this->postJson(storeApi("returns/{$return->id}/messages"), ['message' => 'Hello, any update on this?'], $this->headers)->assertNotFound();
    $this->getJson(storeApi('returns'), $this->headers)->assertJsonCount(0, 'data.returns');

    $other = shopSetup('elsewhere');
    $stranger = shopCustomer($other);
    expect($stranger->id)->toBe($this->customer->id);
    shopActingAs($stranger);
    $this->getJson(storeApi("returns/{$return->id}"), shopHeaders($other->tenant))->assertNotFound();
    $this->getJson(storeApi('returns'), shopHeaders($other->tenant))->assertJsonCount(0, 'data.returns');
});

it('lets the customer reply on the thread', function (): void {
    ($this->deliver)();
    $return = ($this->open)();
    $url = storeApi("returns/{$return->id}/messages");

    $this->postJson($url, ['message' => 'Too short'], $this->headers)->assertJsonValidationErrors('message');
    $this->post($url, ['message' => 'Here is a photo of the colour.', 'photos' => [UploadedFile::fake()->image('colour.webp')]], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.last_message', 'Here is a photo of the colour.')
        ->assertJsonPath('data.timeline.1.author', 'customer')
        ->assertJsonCount(1, 'data.media');

    $event = $return->events()->where('type', ReturnEventType::Message->value)->sole();
    expect($event->customer_visible)->toBeTrue()->and($event->actor_type)->toBe(ActorType::Customer);

    Notification::assertSentTo(app(AdminRepositoryContract::class)->activeWithPermission('returns.view'), OrderAlertNotification::class, fn (OrderAlertNotification $n): bool => $n->eventName === 'admin_return_message');
    Notification::assertNotSentTo($this->customer, OrderCustomerNotification::class, fn (OrderCustomerNotification $n): bool => $n->eventName === 'return_message');
});

it('withdraws a return under review only', function (): void {
    ($this->deliver)();
    $return = ($this->open)();

    $this->postJson(storeApi("returns/{$return->id}/withdraw"), ['note' => 'Found a use for it.'], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.can_withdraw', false);

    expect($return->refresh()->status)->toBe(ReturnStatus::Cancelled)->and($return->cancelled_at)->not->toBeNull();
    Notification::assertSentTo(app(AdminRepositoryContract::class)->activeWithPermission('returns.view'), OrderAlertNotification::class, fn (OrderAlertNotification $n): bool => $n->eventName === 'admin_return_updated');

    $this->postJson(storeApi("returns/{$return->id}/withdraw"), [], $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'withdraw_not_allowed');

    $approved = ($this->open)();
    app(ReturnRequestService::class)->act($approved, new ReturnActionData(ReturnAction::Approve), Actor::system());

    $this->postJson(storeApi("returns/{$approved->id}/withdraw"), [], $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'withdraw_not_allowed');
    expect($approved->refresh()->status)->toBe(ReturnStatus::Approved);
});

it('estimates the refund in the display currency too, keeping USD as the amount', function (): void {
    ($this->deliver)();
    Currency::query()->where('code', 'EGP')->delete();
    Currency::factory()->code('EGP', '50.000000')->create();

    $this->postJson(storeApi("orders/{$this->order->uuid}/returns/estimate"), [
        'reason_code' => 'changed_mind',
        'items' => [['order_item_id' => $this->item->id, 'quantity' => 1]],
    ], $this->headers + ['X-Currency' => 'EGP'])
        ->assertOk()
        ->assertJsonPath('data.items.currency', 'USD')
        ->assertJsonPath('data.total.display.currency', 'EGP');
});
