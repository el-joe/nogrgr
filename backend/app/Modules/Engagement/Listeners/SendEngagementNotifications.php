<?php

namespace App\Modules\Engagement\Listeners;

use App\Modules\Engagement\Enums\ReviewStatus;
use App\Modules\Engagement\Events\ReviewModerated;
use App\Modules\Engagement\Events\ReviewSubmitted;
use App\Modules\Engagement\Events\SupportRequestReceived;
use App\Modules\Engagement\Notifications\ReviewModeratedNotification;
use App\Modules\Engagement\Notifications\StoreEngagementNotification;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Tells the store owners about storefront engagement (runs in the store's tenant
 * context): a support request, a review (pending = needs approval); the review's author
 * hears about the moderation result (decision #124).
 */
class SendEngagementNotifications
{
    public function __construct(
        private readonly VendorStaffRepositoryContract $staff,
        private readonly CustomerRepositoryContract $customers,
    ) {}

    /**
     * Moderation result → the review's author (inbox + push; mail when published).
     */
    public function handleModeration(ReviewModerated $event): void
    {
        $review = $event->review;

        if ($review->status === $event->from || ! in_array($review->status, [ReviewStatus::Approved, ReviewStatus::Rejected], true)) {
            return;
        }

        $customer = $this->customers->find($review->customer_id);

        if (! $customer instanceof Customer) {
            return;
        }

        $review->loadMissing('orderItem');
        $tenant = tenant();
        $base = $tenant instanceof Tenant ? StoreSettingsService::storeUrl($tenant) : rtrim(url('/'), '/');

        $customer->notify(new ReviewModeratedNotification($review->id, $review->status, [
            'store.name' => $tenant instanceof Tenant ? (string) $tenant->name : '',
            'store.url' => $base,
            'product.name' => Str::limit((string) ($review->orderItem->name ?? ''), 120),
            'product.url' => $base.route('storefront.product', ['product' => $review->store_product_id], false),
            'review.rating' => (string) $review->rating,
        ]));
    }

    public function handleSupportRequest(SupportRequestReceived $event): void
    {
        $this->send('support_request', [
            'ticket' => $event->request->ticketNumber(),
            'name' => $event->request->name,
            'email' => $event->request->email,
            'subject' => $event->request->subject->label(),
        ]);
    }

    public function handleReview(ReviewSubmitted $event): void
    {
        $review = $event->review;
        $review->loadMissing('orderItem');

        $this->send($review->status === ReviewStatus::Pending ? 'review_pending' : 'review_published', [
            'author' => $review->author_name,
            'rating' => $review->rating,
            'product' => Str::limit((string) ($review->orderItem->name ?? ''), 80),
        ]);
    }

    /**
     * @param  array<string, string|int>  $parameters
     */
    private function send(string $event, array $parameters): void
    {
        $owners = $this->staff->activeOwners();

        if ($owners->isNotEmpty()) {
            Notification::send($owners, new StoreEngagementNotification($event, $parameters));
        }
    }
}
