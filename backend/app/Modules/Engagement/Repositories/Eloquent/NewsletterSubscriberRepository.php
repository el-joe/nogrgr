<?php

namespace App\Modules\Engagement\Repositories\Eloquent;

use App\Modules\Engagement\Enums\SubscriberSource;
use App\Modules\Engagement\Enums\SubscriberStatus;
use App\Modules\Engagement\Models\NewsletterSubscriber;
use App\Modules\Engagement\Repositories\Contracts\NewsletterSubscriberRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

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

    public function listing(array $filters): Builder
    {
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        $status = SubscriberStatus::tryFrom(is_string($filters['status'] ?? null) ? $filters['status'] : '');
        $source = SubscriberSource::tryFrom(is_string($filters['source'] ?? null) ? $filters['source'] : '');
        $locale = is_string($filters['locale'] ?? null) && $filters['locale'] !== '' ? $filters['locale'] : null;

        return $this->query()
            ->when($status === SubscriberStatus::Subscribed, static fn (Builder $query) => $query->whereNull('unsubscribed_at'))
            ->when($status === SubscriberStatus::Unsubscribed, static fn (Builder $query) => $query->whereNotNull('unsubscribed_at'))
            ->when($source === SubscriberSource::Account, static fn (Builder $query) => $query->whereNotNull('customer_id'))
            ->when($source === SubscriberSource::SignupForm, static fn (Builder $query) => $query->whereNull('customer_id'))
            ->when($locale !== null, static fn (Builder $query) => $query->where('locale', $locale))
            ->when($search !== '', static fn (Builder $query) => $query->where('email', 'like', '%'.addcslashes($search, '%_\\').'%'));
    }

    public function locales(): array
    {
        /** @var list<string> $locales */
        $locales = $this->query()->whereNotNull('locale')->distinct()->orderBy('locale')->pluck('locale')->all();

        return $locales;
    }
}
