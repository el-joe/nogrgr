<?php

namespace App\Modules\Engagement\Services;

use App\Modules\Engagement\Models\NewsletterSubscriber;
use App\Modules\Engagement\Repositories\Contracts\NewsletterSubscriberRepositoryContract;

/**
 * Store newsletter signup (footer, SF-ECO-035, tenant context). Signing up again
 * re-subscribes a past subscriber; an active one is reported as "already subscribed".
 */
class NewsletterService
{
    public function __construct(private readonly NewsletterSubscriberRepositoryContract $subscribers) {}

    /**
     * @return bool true = newly (re)subscribed, false = was already subscribed
     */
    public function subscribe(string $email, ?string $locale = null, ?int $customerId = null): bool
    {
        $email = mb_strtolower(trim($email));
        $existing = $this->subscribers->findByEmail($email);

        if ($existing instanceof NewsletterSubscriber) {
            if ($existing->unsubscribed_at === null) {
                return false;
            }

            $this->subscribers->update($existing, ['unsubscribed_at' => null, 'subscribed_at' => now(), 'locale' => $locale ?? $existing->locale]);

            return true;
        }

        $this->subscribers->create(['email' => $email, 'locale' => $locale, 'customer_id' => $customerId, 'subscribed_at' => now()]);

        return true;
    }

    public function unsubscribe(string $email): void
    {
        $existing = $this->subscribers->findByEmail($email);

        if ($existing instanceof NewsletterSubscriber && $existing->unsubscribed_at === null) {
            $this->subscribers->update($existing, ['unsubscribed_at' => now()]);
        }
    }
}
