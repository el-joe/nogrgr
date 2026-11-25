<?php

namespace App\Shared\Modules;

use App\Shared\Modules\Console\MakeModuleCommand;
use App\Shared\Routing\ModuleRouteRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Registers every module listed in config/modules.php.
 */
class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRouteRegistry::class);

        /** @var list<class-string<ModuleServiceProvider>> $modules */
        $modules = config('modules.modules', []);

        foreach ($modules as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([MakeModuleCommand::class]);
        }
    }
}
