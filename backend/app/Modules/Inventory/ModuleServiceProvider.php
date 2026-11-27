<?php

namespace App\Modules\Inventory;

use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Events\StoreStockChanged;
use App\Modules\Inventory\Listeners\QueueCentralStockAlerts;
use App\Modules\Inventory\Listeners\SendStoreStockAlerts;
use App\Modules\Inventory\Repositories\Contracts\BranchRepositoryContract;
use App\Modules\Inventory\Repositories\Contracts\BranchStockRepositoryContract;
use App\Modules\Inventory\Repositories\Contracts\StockAlertSubscriptionRepositoryContract;
use App\Modules\Inventory\Repositories\Eloquent\BranchRepository;
use App\Modules\Inventory\Repositories\Eloquent\BranchStockRepository;
use App\Modules\Inventory\Repositories\Eloquent\StockAlertSubscriptionRepository;
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
        BranchRepositoryContract::class => BranchRepository::class,
        BranchStockRepositoryContract::class => BranchStockRepository::class,
        StockAlertSubscriptionRepositoryContract::class => StockAlertSubscriptionRepository::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // Stock alerts (decision #124): low / out of stock to the store team, back in stock to subscribers.
        Event::listen(StockChanged::class, QueueCentralStockAlerts::class);
        Event::listen(StoreStockChanged::class, SendStoreStockAlerts::class);
    }
}
