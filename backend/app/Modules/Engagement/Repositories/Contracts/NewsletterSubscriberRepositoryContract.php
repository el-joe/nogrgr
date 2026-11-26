<?php

namespace App\Modules\Engagement\Repositories\Contracts;

use App\Modules\Engagement\Models\NewsletterSubscriber;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Store newsletter subscribers (tenant DB).
 *
 * @extends RepositoryContract<NewsletterSubscriber>
 */
interface NewsletterSubscriberRepositoryContract extends RepositoryContract
{
    public function findByEmail(string $email): ?NewsletterSubscriber;
}
