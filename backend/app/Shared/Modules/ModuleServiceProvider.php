<?php

namespace App\Shared\Modules;

use App\Shared\Routing\ModuleRouteRegistry;
use App\Shared\Routing\Surface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Base provider for every module in app/Modules/<Module>.
 *
 * Auto-loads by convention:
 *  - routes/<surface>.php            → mounted by SurfaceRouter into the surface group
 *  - resources/views                 → view namespace "<kebab-module>::"
 *  - resources/lang                  → translation namespace "<kebab-module>::"
 *  - Database/migrations/central     → central `php artisan migrate`
 *  - Database/migrations/tenant      → `php artisan tenants:migrate`
 *
 * Repository bindings go in the inherited `$bindings` / `$singletons` arrays.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    private ?string $resolvedPath = null;

    public function moduleName(): string
    {
        return class_basename(Str::beforeLast(static::class, '\\'));
    }

    public function moduleKey(): string
    {
        return Str::kebab($this->moduleName());
    }

    public function modulePath(string $path = ''): string
    {
        $this->resolvedPath ??= dirname((string) (new ReflectionClass(static::class))->getFileName());

        return $path === '' ? $this->resolvedPath : $this->resolvedPath.DIRECTORY_SEPARATOR.$path;
    }

    public function register(): void
    {
        $this->registerTenantMigrations();
    }

    public function boot(): void
    {
        $this->bootViews();
        $this->bootTranslations();
        $this->bootCentralMigrations();
        $this->bootRoutes();
    }

    protected function bootViews(): void
    {
        $views = $this->modulePath('resources/views');

        if (is_dir($views)) {
            $this->loadViewsFrom($views, $this->moduleKey());
        }
    }

    protected function bootTranslations(): void
    {
        $lang = $this->modulePath('resources/lang');

        if (is_dir($lang)) {
            $this->loadTranslationsFrom($lang, $this->moduleKey());
            $this->loadJsonTranslationsFrom($lang);
        }
    }

    protected function bootCentralMigrations(): void
    {
        $central = $this->modulePath('Database/migrations/central');

        if (is_dir($central)) {
            $this->loadMigrationsFrom($central);
        }
    }

    protected function registerTenantMigrations(): void
    {
        $tenant = $this->modulePath('Database/migrations/tenant');

        if (! is_dir($tenant)) {
            return;
        }

        /** @var list<string> $paths */
        $paths = (array) config('tenancy.migration_parameters.--path', []);

        if (! in_array($tenant, $paths, true)) {
            $paths[] = $tenant;
            config(['tenancy.migration_parameters.--path' => $paths]);
        }
    }

    protected function bootRoutes(): void
    {
        $registry = $this->app->make(ModuleRouteRegistry::class);

        foreach (Surface::cases() as $surface) {
            $file = $this->modulePath('routes/'.$surface->value.'.php');

            if (is_file($file)) {
                $registry->add($surface, $file);
            }
        }
    }
}
