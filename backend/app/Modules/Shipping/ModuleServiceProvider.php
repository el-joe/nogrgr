<?php

namespace App\Modules\Shipping;

use App\Modules\Catalog\Events\ProductCreated;
use App\Modules\Catalog\Events\ProductUpdated;
use App\Modules\Shipping\Listeners\RecomputeProductFixedShipping;
use App\Modules\Shipping\Repositories\Contracts\DeliveryPopupDayRepositoryContract;
use App\Modules\Shipping\Repositories\Contracts\FixedShippingCostRepositoryContract;
use App\Modules\Shipping\Repositories\Contracts\ProductShippingMapRepositoryContract;
use App\Modules\Shipping\Repositories\Contracts\ShippingCompanyRepositoryContract;
use App\Modules\Shipping\Repositories\Contracts\ShippingRuleRepositoryContract;
use App\Modules\Shipping\Repositories\Contracts\ShippingSettingRepositoryContract;
use App\Modules\Shipping\Repositories\Contracts\ShippingStopPeriodRepositoryContract;
use App\Modules\Shipping\Repositories\Eloquent\DeliveryPopupDayRepository;
use App\Modules\Shipping\Repositories\Eloquent\FixedShippingCostRepository;
use App\Modules\Shipping\Repositories\Eloquent\ProductShippingMapRepository;
use App\Modules\Shipping\Repositories\Eloquent\ShippingCompanyRepository;
use App\Modules\Shipping\Repositories\Eloquent\ShippingRuleRepository;
use App\Modules\Shipping\Repositories\Eloquent\ShippingSettingRepository;
use App\Modules\Shipping\Repositories\Eloquent\ShippingStopPeriodRepository;
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
        FixedShippingCostRepositoryContract::class => FixedShippingCostRepository::class,
        ShippingRuleRepositoryContract::class => ShippingRuleRepository::class,
        ShippingCompanyRepositoryContract::class => ShippingCompanyRepository::class,
        ShippingStopPeriodRepositoryContract::class => ShippingStopPeriodRepository::class,
        DeliveryPopupDayRepositoryContract::class => DeliveryPopupDayRepository::class,
        ShippingSettingRepositoryContract::class => ShippingSettingRepository::class,
        ProductShippingMapRepositoryContract::class => ProductShippingMapRepository::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Event::listen([ProductCreated::class, ProductUpdated::class], RecomputeProductFixedShipping::class);
    }
}
