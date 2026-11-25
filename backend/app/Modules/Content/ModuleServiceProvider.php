<?php

namespace App\Modules\Content;

use App\Modules\Content\Events\ContactMessageReceived;
use App\Modules\Content\Listeners\SendContactNotifications;
use App\Modules\Content\Repositories\Contracts\BlogCategoryRepositoryContract;
use App\Modules\Content\Repositories\Contracts\BlogPostRepositoryContract;
use App\Modules\Content\Repositories\Contracts\ContactMessageRepositoryContract;
use App\Modules\Content\Repositories\Contracts\FaqRepositoryContract;
use App\Modules\Content\Repositories\Contracts\NewsletterSubscriberRepositoryContract;
use App\Modules\Content\Repositories\Contracts\PageRepositoryContract;
use App\Modules\Content\Repositories\Eloquent\BlogCategoryRepository;
use App\Modules\Content\Repositories\Eloquent\BlogPostRepository;
use App\Modules\Content\Repositories\Eloquent\ContactMessageRepository;
use App\Modules\Content\Repositories\Eloquent\FaqRepository;
use App\Modules\Content\Repositories\Eloquent\NewsletterSubscriberRepository;
use App\Modules\Content\Repositories\Eloquent\PageRepository;
use App\Modules\Content\Services\CentralSiteService;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        BlogCategoryRepositoryContract::class => BlogCategoryRepository::class,
        BlogPostRepositoryContract::class => BlogPostRepository::class,
        PageRepositoryContract::class => PageRepository::class,
        FaqRepositoryContract::class => FaqRepository::class,
        NewsletterSubscriberRepositoryContract::class => NewsletterSubscriberRepository::class,
        ContactMessageRepositoryContract::class => ContactMessageRepository::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    public $singletons = [
        CentralSiteService::class => CentralSiteService::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Blade::anonymousComponentPath(__DIR__.'/resources/views/components/central', 'central');
        Event::listen(ContactMessageReceived::class, SendContactNotifications::class);
    }
}
