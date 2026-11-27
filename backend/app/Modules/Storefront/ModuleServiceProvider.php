<?php

namespace App\Modules\Storefront;

use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Storefront\Console\DemoStoreCommand;
use App\Modules\Storefront\Console\SyncStorePagesCommand;
use App\Modules\Storefront\Console\ThemeDocsCommand;
use App\Modules\Storefront\Http\Middleware\ApplyCustomThemeSecurityHeaders;
use App\Modules\Storefront\Http\Middleware\EnsureStoreAvailable;
use App\Modules\Storefront\Listeners\EnsureStorePages;
use App\Modules\Storefront\Repositories\Contracts\BannerRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\CustomThemeRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\HiddenBannerRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\StoreBannerRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\StorefrontCatalogRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\StoreMenuLinkRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\StorePageRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\StoreSocialLinkRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\ThemeRepositoryContract;
use App\Modules\Storefront\Repositories\Eloquent\BannerRepository;
use App\Modules\Storefront\Repositories\Eloquent\CustomThemeRepository;
use App\Modules\Storefront\Repositories\Eloquent\HiddenBannerRepository;
use App\Modules\Storefront\Repositories\Eloquent\StoreBannerRepository;
use App\Modules\Storefront\Repositories\Eloquent\StorefrontCatalogRepository;
use App\Modules\Storefront\Repositories\Eloquent\StoreMenuLinkRepository;
use App\Modules\Storefront\Repositories\Eloquent\StorePageRepository;
use App\Modules\Storefront\Repositories\Eloquent\StoreSocialLinkRepository;
use App\Modules\Storefront\Repositories\Eloquent\ThemeRepository;
use App\Modules\Storefront\Services\Appearance\StorefrontAppearance;
use App\Modules\Storefront\Services\Catalog\BadgeShelves;
use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Modules\Storefront\Services\Catalog\ProductCards;
use App\Modules\Storefront\Services\Catalog\ProductIndex;
use App\Modules\Storefront\Services\CustomThemes\CustomThemePackageStore;
use App\Modules\Storefront\Services\CustomThemes\CustomTwigThemeRenderer;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Services\Themes\ThemePageService;
use App\Modules\Storefront\Services\Themes\ThemePartService;
use App\Modules\Storefront\Support\CustomThemes\CustomThemeCsp;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Shared\Routing\ModuleRouteRegistry;
use App\Shared\Routing\Surface;
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
        StoreMenuLinkRepositoryContract::class => StoreMenuLinkRepository::class,
        StoreSocialLinkRepositoryContract::class => StoreSocialLinkRepository::class,
    ];

    public function register(): void
    {
        parent::register();

        // One storefront context per request (country, currency, theme).
        $this->app->scoped(StorefrontContext::class);

        // Catalog read model memoised per request (sf-catalog).
        $this->app->scoped(ProductIndex::class);
        $this->app->scoped(CategoryCatalog::class);
        $this->app->scoped(BadgeShelves::class);
        $this->app->scoped(ProductCards::class);

        // Store appearance read model (promo bar, links, fonts) memoised per request (VEN-034).
        $this->app->scoped(StorefrontAppearance::class);

        // Vendor themes page model (memoises catalogue + store setting) once per request.
        $this->app->scoped(ThemePageService::class);
        $this->app->scoped(ThemePartService::class);

        // Custom Twig themes (decision #121): one CSP nonce, one compiled environment and
        // one live-version lookup per request.
        $this->app->scoped(CustomThemeCsp::class);
        $this->app->scoped(CustomTwigThemeRenderer::class);
        $this->app->scoped(CustomThemePackageStore::class);
        $this->app->scoped(CustomThemeRepositoryContract::class, CustomThemeRepository::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Vendor › Pages / Appearance / Tracking pixels and the storefront appearance stylesheet (VEN-033/034/083).
        $registry = $this->app->make(ModuleRouteRegistry::class);
        $registry->add(Surface::Vendor, $this->modulePath('routes/vendor-appearance.php'));
        $registry->add(Surface::Storefront, $this->modulePath('routes/storefront-appearance.php'));

        // Custom Twig themes (VEN-036, ADM-089, SF-ALL-006, decision #121).
        $registry->add(Surface::Vendor, $this->modulePath('routes/vendor-custom-themes.php'));
        $registry->add(Surface::Admin, $this->modulePath('routes/admin-custom-themes.php'));
        $registry->add(Surface::Storefront, $this->modulePath('routes/storefront-custom-themes.php'));

        // Theme catalogue admin (ADM-086 / 087 / 088, decisions #119 / #120).
        $registry->add(Surface::Admin, $this->modulePath('routes/admin-themes.php'));

        $this->app['router']->pushMiddlewareToGroup('web', ApplyCustomThemeSecurityHeaders::class);

        // System CMS pages for every new store (sf-data).
        Event::listen(TenantProvisioned::class, EnsureStorePages::class);

        // Not-launched / suspended store gate for `storefront.*` routes (sf-checkout).
        $this->app['router']->pushMiddlewareToGroup('web', EnsureStoreAvailable::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncStorePagesCommand::class, DemoStoreCommand::class, ThemeDocsCommand::class]);
        }
    }
}
