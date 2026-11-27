<?php

namespace App\Modules\Platform\Support;

use App\Modules\Localization\Services\StaticTexts\StaticTextResolver;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * The cache operations behind Admin › Cache tools (ADM-114). Central ones run the
 * framework commands; store ones flush the store's tagged cache (CacheTenancyBootstrapper
 * tag), its static-text cache (StaticTextResolver, inside the store) and its compiled
 * custom Twig templates.
 */
class CacheClearer
{
    /** Directory of compiled custom Twig templates on the custom-themes disk. */
    public const COMPILED_DIR = 'compiled';

    public function __construct(private readonly Application $app) {}

    public function application(): void
    {
        Artisan::call('cache:clear');
    }

    /**
     * Clear config + route caches and rebuild them when the app ran cached.
     */
    public function configAndRoutes(): void
    {
        $configCached = $this->app->configurationIsCached();
        $routesCached = $this->app->routesAreCached();

        Artisan::call('config:clear');
        Artisan::call('route:clear');

        if ($configCached) {
            Artisan::call('config:cache');
        }

        if ($routesCached) {
            Artisan::call('route:cache');
        }
    }

    /**
     * Compiled Blade views and every compiled custom Twig template.
     */
    public function views(): void
    {
        Artisan::call('view:clear');
        Storage::disk('custom-themes')->deleteDirectory(self::COMPILED_DIR);
    }

    /**
     * The store's tagged cache entries (storefront pages, settings read in the store).
     */
    public function storePages(Tenant $tenant): void
    {
        $store = app('cache')->store();

        if ($store->getStore() instanceof TaggableStore) {
            $store->tags([config('tenancy.cache.tag_base').$tenant->getTenantKey()])->flush();
        }
    }

    /**
     * The store's static texts (theme / app strings) in every locale.
     */
    public function storeTranslations(Tenant $tenant): void
    {
        if (! $tenant->status->isOperational()) {
            return;
        }

        $tenant->run(static function (): void {
            app(StaticTextResolver::class)->forget();
        });
    }

    /**
     * Compiled custom Twig templates of the store (rebuilt on the next render).
     */
    public function storeThemes(Tenant $tenant): void
    {
        Storage::disk('custom-themes')->deleteDirectory(self::COMPILED_DIR.'/'.$tenant->getTenantKey());
    }
}
