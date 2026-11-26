<?php

namespace App\Modules\Storefront;

use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Storefront\Console\DemoStoreCommand;
use App\Modules\Storefront\Console\SyncStorePagesCommand;
use App\Modules\Storefront\Http\Middleware\EnsureStoreAvailable;
use App\Modules\Storefront\Listeners\EnsureStorePages;
use App\Modules\Storefront\Repositories\Contracts\BannerRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\HiddenBannerRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\StoreBannerRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\StorefrontCatalogRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\StorePageRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\ThemeRepositoryContract;
use App\Modules\Storefront\Repositories\Eloquent\BannerRepository;
use App\Modules\Storefront\Repositories\Eloquent\HiddenBannerRepository;
use App\Modules\Storefront\Repositories\Eloquent\StoreBannerRepository;
use App\Modules\Storefront\Repositories\Eloquent\StorefrontCatalogRepository;
use App\Modules\Storefront\Repositories\Eloquent\StorePageRepository;
use App\Modules\Storefront\Repositories\Eloquent\ThemeRepository;
use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Modules\Storefront\Services\Catalog\ProductCards;
use App\Modules\Storefront\Services\Catalog\ProductIndex;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        ThemeRepositoryContract::class => ThemeRepository::class,
        StorefrontCatalogRepositoryContract::class => StorefrontCatalogRepository::class,
        BannerRepositoryContract::class => BannerRepository::class,
        StoreBannerRepositoryContract::class => StoreBannerRepository::class,
        HiddenBannerRepositoryContract::class => HiddenBannerRepository::class,
        StorePageRepositoryContract::class => StorePageRepository::class,
    ];

    public function register(): void
    {
        parent::register();

        // One storefront context per request (country, currency, theme).
        $this->app->scoped(StorefrontContext::class);

        // Catalog read model memoised per request (sf-catalog).
        $this->app->scoped(ProductIndex::class);
        $this->app->scoped(CategoryCatalog::class);
        $this->app->scoped(ProductCards::class);
    }

    public function boot(): void
    {
        parent::boot();

        // System CMS pages for every new store (sf-data).
        Event::listen(TenantProvisioned::class, EnsureStorePages::class);

        // Not-launched / suspended store gate for `storefront.*` routes (sf-checkout).
        $this->app['router']->pushMiddlewareToGroup('web', EnsureStoreAvailable::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncStorePagesCommand::class, DemoStoreCommand::class]);
        }
    }
}
