<?php

namespace App\Modules\Engagement;

use App\Modules\Engagement\Events\ReviewSubmitted;
use App\Modules\Engagement\Events\SupportRequestReceived;
use App\Modules\Engagement\Listeners\SendEngagementNotifications;
use App\Modules\Engagement\Repositories\Contracts\FavoriteRepositoryContract;
use App\Modules\Engagement\Repositories\Contracts\NewsletterSubscriberRepositoryContract;
use App\Modules\Engagement\Repositories\Contracts\ProductReviewRepositoryContract;
use App\Modules\Engagement\Repositories\Contracts\SupportRequestRepositoryContract;
use App\Modules\Engagement\Repositories\Eloquent\FavoriteRepository;
use App\Modules\Engagement\Repositories\Eloquent\NewsletterSubscriberRepository;
use App\Modules\Engagement\Repositories\Eloquent\ProductReviewRepository;
use App\Modules\Engagement\Repositories\Eloquent\SupportRequestRepository;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        ProductReviewRepositoryContract::class => ProductReviewRepository::class,
        FavoriteRepositoryContract::class => FavoriteRepository::class,
        SupportRequestRepositoryContract::class => SupportRequestRepository::class,
        NewsletterSubscriberRepositoryContract::class => NewsletterSubscriberRepository::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Event::listen(SupportRequestReceived::class, [SendEngagementNotifications::class, 'handleSupportRequest']);
        Event::listen(ReviewSubmitted::class, [SendEngagementNotifications::class, 'handleReview']);

        /* Storefront forms: per visitor IP and store host. */
        RateLimiter::for('storefront-forms', static fn (Request $request): Limit => Limit::perMinute(5)->by('sf-forms:'.$request->getHost().':'.$request->ip()));
        RateLimiter::for('storefront-reviews', static fn (Request $request): Limit => Limit::perMinute(10)->by('sf-reviews:'.$request->getHost().':'.$request->ip()));
    }
}
