<?php

namespace App\Providers;

use App\Shared\Validation\LocalizedAttributeValidator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Factory as ValidationFactory;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->bootModuleFactories();
        $this->bootBladeNamespaces();
        $this->bootParallelTestingIsolation();
        $this->bootValidator();
    }

    /**
     * Validator that names per-locale fields ("title.en" → "title (English)").
     */
    private function bootValidator(): void
    {
        $this->app->afterResolving('validator', static function (ValidationFactory $factory): void {
            $factory->resolver(static fn ($translator, array $data, array $rules, array $messages, array $attributes): LocalizedAttributeValidator => new LocalizedAttributeValidator($translator, $data, $rules, $messages, $attributes));
        });
    }

    /**
     * App\Modules\X\Models\Y → App\Modules\X\Database\Factories\YFactory.
     */
    private function bootModuleFactories(): void
    {
        Factory::guessFactoryNamesUsing(static function (string $modelName): string {
            if (Str::startsWith($modelName, 'App\\Modules\\')) {
                $module = Str::before(Str::after($modelName, 'App\\Modules\\'), '\\');

                return 'App\\Modules\\'.$module.'\\Database\\Factories\\'.class_basename($modelName).'Factory';
            }

            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });
    }

    /**
     * <x-ui::button> → resources/views/components/ui/button.blade.php
     * <x-sf::section.hero> → resources/views/components/storefront/section/hero.blade.php
     */
    private function bootBladeNamespaces(): void
    {
        Blade::anonymousComponentPath(resource_path('views/components/ui'), 'ui');
        Blade::anonymousComponentPath(resource_path('views/components/storefront'), 'sf');
    }

    /**
     * Each parallel test process gets its own tenant storage folders, cache prefix
     * and Redis prefix (central + tenant DB names are suffixed elsewhere:
     * Laravel's TestDatabases and App\Shared\Tenancy\TenantDatabaseName).
     */
    private function bootParallelTestingIsolation(): void
    {
        /*
         * Use paratest's per-worker unique token (e.g. "3_6571f0c2a1b2c") instead of the
         * plain 1..N worker number, so two concurrent parallel runs on one machine (other
         * agents, CI on the same host) never share central or tenant test databases.
         * tests/bootstrap.php drops the worker's databases when the worker exits.
         */
        ParallelTesting::resolveTokenUsing(static function (): int|string|false {
            $unique = $_SERVER['UNIQUE_TEST_TOKEN'] ?? getenv('UNIQUE_TEST_TOKEN');

            if (is_string($unique) && preg_match('/^[A-Za-z0-9_]+$/', $unique) === 1) {
                return $unique;
            }

            return $_SERVER['TEST_TOKEN'] ?? false;
        });

        ParallelTesting::setUpTestCase(function (int|string $token): void {
            $suffix = '_test_'.$token;

            config([
                'tenancy.filesystem.suffix_base' => 'tenant'.$suffix.'_',
                'tenancy.cache.tag_base' => 'tenant'.$suffix.'_',
                'tenancy.redis.prefix_base' => 'tenant'.$suffix.'_',
                'cache.prefix' => Str::finish((string) config('cache.prefix'), $suffix.'_'),
                'database.redis.options.prefix' => Str::finish((string) config('database.redis.options.prefix'), 'test'.$token.'_'),
            ]);

            $this->app->forgetInstance('redis');
            Redis::clearResolvedInstances();
        });

    }
}
