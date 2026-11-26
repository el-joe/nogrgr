<?php

namespace App\Modules\Engagement\Repositories\Eloquent;

use App\Modules\Engagement\Models\NewsletterSubscriber;
use App\Modules\Engagement\Repositories\Contracts\NewsletterSubscriberRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<NewsletterSubscriber>
 */
class NewsletterSubscriberRepository extends BaseRepository implements NewsletterSubscriberRepositoryContract
{
    protected function model(): string
    {
        return NewsletterSubscriber::class;
    }

    public function findByEmail(string $email): ?NewsletterSubscriber
    {
        return $this->query()->where('email', mb_strtolower(trim($email)))->first();
    }
}
