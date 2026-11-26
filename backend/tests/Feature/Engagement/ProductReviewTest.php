<?php

use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Engagement\Enums\ReviewStatus;
use App\Modules\Engagement\Events\ReviewModerated;
use App\Modules\Engagement\Models\ProductReview;
use App\Modules\Engagement\Notifications\StoreEngagementNotification;
use App\Modules\Engagement\Services\Reviews\ReviewService;
use App\Modules\Engagement\Services\Reviews\ReviewSettingsService;
use App\Modules\Engagement\Services\Reviews\ReviewSummaryReader;
use App\Modules\Identity\Models\Customer;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

require_once dirname(__DIR__).'/Commerce/StoreApiHelpers.php';
require_once __DIR__.'/EngagementHelpers.php';

/*
| Product reviews (SF-ECO-019a / 028d): only a customer with a delivered order line of
| the product may review it, once per order line; list + summary + eligibility, approval
| setting, moderation (approve / reject / hide), summaries for many products, owners told.
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('reviews');
    [$this->product, $this->other, $this->customer, $this->stranger] = $this->tenant->run(fn (): array => [
        StoreProduct::factory()->create(['price' => Money::of('15.00'), 'stock' => 10]),
        StoreProduct::factory()->create(['price' => Money::of('9.00'), 'stock' => 10]),
        Customer::factory()->create(['name' => 'Sara Ahmed Ali']),
        Customer::factory()->create(),
    ]);
    $this->headers = shopHeaders($this->tenant);
    $this->reviews = fn (?int $product = null, string $query = ''): string => storeApi('products/'.($product ?? $this->product->id).'/reviews'.$query);
});

it('lists no reviews and tells guests to sign in', function (): void {
    $this->getJson(($this->reviews)(), $this->headers)
        ->assertOk()
        ->assertJsonPath('data.summary.count', 0)
        ->assertJsonPath('data.summary.average', 0)
        ->assertJsonPath('data.summary.distribution.5', 0)
        ->assertJsonPath('data.items', [])
        ->assertJsonPath('data.meta.next_page_url', null)
        ->assertJsonPath('data.eligibility', ['can_write' => false, 'reason' => 'guest']);

    $this->postJson(($this->reviews)(), ['rating' => 5, 'body' => 'Great product'], $this->headers)->assertUnauthorized()->assertJsonPath('data.reason', 'guest');
    $this->getJson(($this->reviews)(999999), $this->headers)->assertNotFound();
});

it('only lets customers review delivered order lines, once per line', function (): void {
    Notification::fake();
    $owner = $this->createVendorOwner($this->tenant);
    $processing = $this->tenant->run(fn () => engagementOrderLine($this->customer, $this->product, OrderStatus::Processing));
    shopActingAs($this->customer);

    $this->getJson(($this->reviews)(), $this->headers)->assertJsonPath('data.eligibility.reason', 'not_purchased');
    $this->postJson(($this->reviews)(), ['rating' => 5, 'body' => 'Not yet here'], $this->headers)->assertUnprocessable()->assertJsonPath('data.reason', 'not_purchased');
    $this->postJson(storeApi("orders/{$processing->order->uuid}/items/{$processing->id}/review"), ['rating' => 5, 'body' => 'Not yet here'], $this->headers)
        ->assertUnprocessable()->assertJsonPath('data.reason', 'not_delivered');

    $line = $this->tenant->run(fn () => engagementOrderLine($this->customer, $this->product));
    $this->getJson(($this->reviews)(), $this->headers)->assertJsonPath('data.eligibility', ['can_write' => true, 'reason' => null]);
    $this->postJson(($this->reviews)(), ['rating' => 6, 'body' => 'x'], $this->headers)->assertUnprocessable()->assertJsonValidationErrors(['rating', 'body']);

    $this->postJson(($this->reviews)(), ['rating' => 4, 'title' => 'Solid <b>oven</b>', 'body' => 'Heats evenly and looks great.'], $this->headers)
        ->assertCreated()
        ->assertJsonPath('message', __st('reviews.messages.published'))
        ->assertJsonPath('data.review.author', 'Sara A.')
        ->assertJsonPath('data.review.title', 'Solid oven')
        ->assertJsonPath('data.review.verified_purchase', true)
        ->assertJsonPath('data.summary.count', 1)
        ->assertJsonPath('data.summary.average', 4);

    $this->tenant->run(fn () => expect(ProductReview::query()->sole()->order_item_id)->toBe($line->id));
    $this->getJson(($this->reviews)(), $this->headers)->assertJsonPath('data.eligibility.reason', 'already_reviewed')->assertJsonCount(1, 'data.items');
    $this->postJson(($this->reviews)(), ['rating' => 5, 'body' => 'Second time'], $this->headers)->assertUnprocessable()->assertJsonPath('data.reason', 'already_reviewed');
    $this->postJson(storeApi("orders/{$line->order->uuid}/items/{$line->id}/review"), ['rating' => 5, 'body' => 'Second time'], $this->headers)
        ->assertUnprocessable()->assertJsonPath('data.reason', 'already_reviewed');

    // A second delivered order of the same product is a new line → one more review, from the order details modal.
    $again = $this->tenant->run(fn () => engagementOrderLine($this->customer, $this->product));
    $this->postJson(storeApi("orders/{$again->order->uuid}/items/{$again->id}/review"), ['rating' => 2, 'body' => 'Second unit was scratched.'], $this->headers)
        ->assertCreated()->assertJsonPath('data.summary.count', 2)->assertJsonPath('data.summary.average', 3);

    // Another customer's order is not found.
    shopActingAs($this->stranger);
    $this->postJson(storeApi("orders/{$again->order->uuid}/items/{$again->id}/review"), ['rating' => 1, 'body' => 'Not my order'], $this->headers)->assertNotFound();

    Notification::assertSentTo($owner, StoreEngagementNotification::class, fn (StoreEngagementNotification $notification): bool => $notification->eventName === 'review_published');
});

it('holds reviews for approval when the store requires it and moderates them', function (): void {
    Event::fake([ReviewModerated::class]);
    $this->tenant->run(function (): void {
        app(ReviewSettingsService::class)->setRequiresApproval(true);
        engagementOrderLine($this->customer, $this->product);
    });
    shopActingAs($this->customer);

    $this->postJson(($this->reviews)(), ['rating' => 5, 'body' => 'Waiting for the store.'], $this->headers)
        ->assertCreated()
        ->assertJsonPath('message', __st('reviews.messages.pending'))
        ->assertJsonPath('data.review.status', 'pending')
        ->assertJsonPath('data.summary.count', 0);
    $this->getJson(($this->reviews)(), $this->headers)->assertJsonCount(0, 'data.items');

    $this->tenant->run(function (): void {
        $review = ProductReview::query()->sole();
        $service = app(ReviewService::class);

        $service->approve($review);
        expect(app(ReviewSummaryReader::class)->summary($this->product->id)['count'])->toBe(1);

        $service->hide($review, 'Contains a phone number');
        expect($review->fresh()?->status)->toBe(ReviewStatus::Hidden)
            ->and($review->fresh()?->moderation_note)->toBe('Contains a phone number')
            ->and(app(ReviewSummaryReader::class)->summary($this->product->id)['count'])->toBe(0);

        $service->reject($review);
        expect($review->fresh()?->status)->toBe(ReviewStatus::Rejected);
    });

    Event::assertDispatchedTimes(ReviewModerated::class, 3);
});

it('pages, sorts and filters reviews and summarises many products in one call', function (): void {
    $this->tenant->run(function (): void {
        foreach ([5, 4, 4, 1] as $index => $rating) {
            $line = engagementOrderLine(Customer::factory()->create(), $this->product);
            ProductReview::factory()->create([
                'store_product_id' => $this->product->id, 'customer_id' => $line->order->customer_id, 'order_id' => $line->order_id,
                'order_item_id' => $line->id, 'rating' => $rating, 'created_at' => now()->subDays($index),
            ]);
        }

        $line = engagementOrderLine(Customer::factory()->create(), $this->other);
        ProductReview::factory()->create(['store_product_id' => $this->other->id, 'customer_id' => $line->order->customer_id, 'order_id' => $line->order_id, 'order_item_id' => $line->id, 'rating' => 5]);
        $line = engagementOrderLine(Customer::factory()->create(), $this->other);
        ProductReview::factory()->pending()->create(['store_product_id' => $this->other->id, 'customer_id' => $line->order->customer_id, 'order_id' => $line->order_id, 'order_item_id' => $line->id, 'rating' => 1]);

        $reader = app(ReviewSummaryReader::class);
        expect($reader->summaries([$this->product->id, $this->other->id, 999]))->toBe([
            $this->product->id => ['average' => 3.5, 'count' => 4],
            $this->other->id => ['average' => 5.0, 'count' => 1],
        ])->and($reader->summary($this->product->id)['distribution'])->toBe([5 => 1, 4 => 2, 3 => 0, 2 => 0, 1 => 1])
            ->and($reader->topRatedIds(10))->toBe([$this->other->id, $this->product->id])
            ->and($reader->topRatedIds(10, 2))->toBe([$this->product->id]);
    });

    $page = $this->getJson(($this->reviews)(null, '?per_page=3'), $this->headers)->assertOk();
    $page->assertJsonCount(3, 'data.items')->assertJsonPath('data.meta.last_page', 2)->assertJsonPath('data.items.0.rating', 5);
    expect($page->json('data.meta.next_page_url'))->toContain('page=2');

    $this->getJson(($this->reviews)(null, '?per_page=3&page=2'), $this->headers)->assertJsonCount(1, 'data.items')->assertJsonPath('data.meta.next_page_url', null);
    $this->getJson(($this->reviews)(null, '?sort=lowest'), $this->headers)->assertJsonPath('data.items.0.rating', 1);
    $this->getJson(($this->reviews)(null, '?sort=highest&rating=4'), $this->headers)->assertJsonCount(2, 'data.items');
    $this->getJson(($this->reviews)(null, '?sort=random'), $this->headers)->assertUnprocessable()->assertJsonValidationErrors('sort');
});
