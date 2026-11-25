<?php

namespace App\Modules\Inventory;

use App\Modules\Inventory\Repositories\Contracts\BranchRepositoryContract;
use App\Modules\Inventory\Repositories\Contracts\BranchStockRepositoryContract;
use App\Modules\Inventory\Repositories\Eloquent\BranchRepository;
use App\Modules\Inventory\Repositories\Eloquent\BranchStockRepository;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        BranchRepositoryContract::class => BranchRepository::class,
        BranchStockRepositoryContract::class => BranchStockRepository::class,
    ];
}
