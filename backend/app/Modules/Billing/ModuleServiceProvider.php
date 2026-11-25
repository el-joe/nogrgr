<?php

namespace App\Modules\Billing;

use App\Modules\Billing\Repositories\Contracts\AddonPurchaseRepositoryContract;
use App\Modules\Billing\Repositories\Eloquent\AddonPurchaseRepository;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        AddonPurchaseRepositoryContract::class => AddonPurchaseRepository::class,
    ];
}
