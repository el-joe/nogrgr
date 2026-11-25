<?php

namespace App\Modules\Content\Services;

use App\Modules\Content\Enums\SubscriberStatus;
use App\Modules\Content\Models\NewsletterSubscriber;
use App\Modules\Content\Notifications\NewsletterConfirmationNotification;
use App\Modules\Content\Repositories\Contracts\NewsletterSubscriberRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Footer newsletter (WEB-015, ADM-084) with double opt-in: subscribe → pending + mail
 * with a confirm link; the same token confirms and unsubscribes. A confirmed address
 * is never re-mailed; a pending one is re-mailed at most once a minute.
 */
class NewsletterService extends BaseService
{
    public const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(private readonly NewsletterSubscriberRepositoryContract $subscribers) {}

    /**
     * @return 'pending'|'already_subscribed'
     */
    public function subscribe(string $email, string $locale, ?string $ip): string
    {
        $email = mb_strtolower(trim($email));
        $subscriber = $this->subscribers->findByEmail($email);

        if ($subscriber?->status === SubscriberStatus::Subscribed) {
            return 'already_subscribed';
        }

        if ($subscriber === null) {
            /** @var NewsletterSubscriber $subscriber */
            $subscriber = $this->subscribers->create([
                'email' => $email,
                'locale' => $locale,
                'status' => SubscriberStatus::Pending,
                'token' => $this->newToken(),
                'ip_address' => $ip,
            ]);
        } elseif ($subscriber->status === SubscriberStatus::Unsubscribed) {
            /** @var NewsletterSubscriber $subscriber */
            $subscriber = $this->subscribers->update($subscriber, [
                'status' => SubscriberStatus::Pending,
                'token' => $this->newToken(),
                'locale' => $locale,
                'unsubscribed_at' => null,
                'confirmation_sent_at' => null,
            ]);
        } elseif ($subscriber->confirmation_sent_at?->gt(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))) {
            return 'pending';
        }

        $subscriber->notify(new NewsletterConfirmationNotification);
        $this->subscribers->update($subscriber, ['confirmation_sent_at' => now()]);

        return 'pending';
    }

    public function confirm(string $token): ?NewsletterSubscriber
    {
        $subscriber = $this->subscribers->findByToken($token);

        if ($subscriber === null || $subscriber->status === SubscriberStatus::Unsubscribed) {
            return null;
        }

        if ($subscriber->status === SubscriberStatus::Pending) {
            /** @var NewsletterSubscriber $subscriber */
            $subscriber = $this->subscribers->update($subscriber, ['status' => SubscriberStatus::Subscribed, 'confirmed_at' => now()]);
        }

        return $subscriber;
    }

    public function unsubscribe(string $token): ?NewsletterSubscriber
    {
        $subscriber = $this->subscribers->findByToken($token);

        if ($subscriber === null) {
            return null;
        }

        if ($subscriber->status !== SubscriberStatus::Unsubscribed) {
            /** @var NewsletterSubscriber $subscriber */
            $subscriber = $this->subscribers->update($subscriber, ['status' => SubscriberStatus::Unsubscribed, 'unsubscribed_at' => now()]);
        }

        return $subscriber;
    }

    public function delete(NewsletterSubscriber $subscriber): void
    {
        $this->subscribers->delete($subscriber);
    }

    /**
     * CSV of the subscribers matching the admin filter (status), newest first.
     */
    public function export(?SubscriberStatus $status): StreamedResponse
    {
        $query = $this->subscribers->query()
            ->when($status !== null, fn ($builder) => $builder->where('status', $status?->value))
            ->orderByDesc('id');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                __('content::admin.newsletter.columns.email'),
                __('content::admin.newsletter.columns.status'),
                __('content::admin.newsletter.columns.locale'),
                __('content::admin.newsletter.columns.subscribed_at'),
                __('content::admin.newsletter.columns.confirmed_at'),
            ]);

            $query->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $subscriber) {
                    /** @var NewsletterSubscriber $subscriber */
                    fputcsv($out, [
                        $subscriber->email,
                        $subscriber->status->label(),
                        $subscriber->locale,
                        $subscriber->created_at?->toDateTimeString(),
                        $subscriber->confirmed_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function newToken(): string
    {
        return Str::random(64);
    }
}
