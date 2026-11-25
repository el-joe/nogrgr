<?php

namespace App\Modules\Storefront;

use App\Modules\Storefront\Repositories\Contracts\ThemeRepositoryContract;
use App\Modules\Storefront\Repositories\Eloquent\ThemeRepository;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        ThemeRepositoryContract::class => ThemeRepository::class,
    ];
}
