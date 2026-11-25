<?php

namespace App\Modules\Commerce;

use App\Modules\Commerce\Repositories\Contracts\CouponRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\FlashSaleRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\HiddenPromotionRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\StoreCouponRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\StoreFlashSaleRepositoryContract;
use App\Modules\Commerce\Repositories\Eloquent\CouponRepository;
use App\Modules\Commerce\Repositories\Eloquent\FlashSaleRepository;
use App\Modules\Commerce\Repositories\Eloquent\HiddenPromotionRepository;
use App\Modules\Commerce\Repositories\Eloquent\StoreCouponRepository;
use App\Modules\Commerce\Repositories\Eloquent\StoreFlashSaleRepository;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        CouponRepositoryContract::class => CouponRepository::class,
        StoreCouponRepositoryContract::class => StoreCouponRepository::class,
        FlashSaleRepositoryContract::class => FlashSaleRepository::class,
        StoreFlashSaleRepositoryContract::class => StoreFlashSaleRepository::class,
        HiddenPromotionRepositoryContract::class => HiddenPromotionRepository::class,
    ];
}
