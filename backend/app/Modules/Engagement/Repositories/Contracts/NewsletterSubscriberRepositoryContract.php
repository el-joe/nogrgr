<?php

namespace App\Modules\Engagement\Repositories\Contracts;

use App\Modules\Engagement\Models\NewsletterSubscriber;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Store newsletter subscribers (tenant DB).
 *
 * @extends RepositoryContract<NewsletterSubscriber>
 */
interface NewsletterSubscriberRepositoryContract extends RepositoryContract
{
    public function findByEmail(string $email): ?NewsletterSubscriber;

    /**
     * Vendor list (VEN-026): filtered by search (e-mail), status, source and language.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<NewsletterSubscriber>
     */
    public function listing(array $filters): Builder;

    /**
     * Languages present on subscribers (for the language filter).
     *
     * @return list<string>
     */
    public function locales(): array;
}
