<?php

namespace App\Modules\Content\Repositories\Contracts;

use App\Modules\Content\Models\NewsletterSubscriber;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * @extends RepositoryContract<NewsletterSubscriber>
 */
interface NewsletterSubscriberRepositoryContract extends RepositoryContract
{
    public function findByEmail(string $email): ?NewsletterSubscriber;

    public function findByToken(string $token): ?NewsletterSubscriber;

    /**
     * status value => count, every status present.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array;
}
