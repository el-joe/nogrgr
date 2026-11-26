<?php

namespace App\Modules\Engagement\Listeners;

use App\Modules\Engagement\Enums\ReviewStatus;
use App\Modules\Engagement\Events\ReviewSubmitted;
use App\Modules\Engagement\Events\SupportRequestReceived;
use App\Modules\Engagement\Notifications\StoreEngagementNotification;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Tells the store owners about storefront engagement (runs in the store's tenant
 * context): a support request, a review (pending = needs approval).
 */
class SendEngagementNotifications
{
    public function __construct(private readonly VendorStaffRepositoryContract $staff) {}

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
