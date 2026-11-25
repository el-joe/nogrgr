<?php

namespace App\Modules\Content\Repositories\Eloquent;

use App\Modules\Content\Enums\SubscriberStatus;
use App\Modules\Content\Models\NewsletterSubscriber;
use App\Modules\Content\Repositories\Contracts\NewsletterSubscriberRepositoryContract;
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
        return NewsletterSubscriber::query()->where('email', mb_strtolower(trim($email)))->first();
    }

    public function findByToken(string $token): ?NewsletterSubscriber
    {
        return NewsletterSubscriber::query()->where('token', $token)->first();
    }

    public function statusCounts(): array
    {
        $counts = NewsletterSubscriber::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $result = [];

        foreach (SubscriberStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }
}
