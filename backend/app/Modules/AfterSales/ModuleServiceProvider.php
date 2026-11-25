<?php

namespace App\Modules\AfterSales;

use App\Modules\AfterSales\Events\RefundCompleted;
use App\Modules\AfterSales\Listeners\RefundCancelledOrder;
use App\Modules\AfterSales\Listeners\RequestAffiliateReversal;
use App\Modules\AfterSales\Listeners\SendAfterSalesNotifications;
use App\Modules\AfterSales\Repositories\Contracts\RefundRepositoryContract;
use App\Modules\AfterSales\Repositories\Contracts\ReturnPolicyRepositoryContract;
use App\Modules\AfterSales\Repositories\Contracts\ReturnRequestRepositoryContract;
use App\Modules\AfterSales\Repositories\Eloquent\RefundRepository;
use App\Modules\AfterSales\Repositories\Eloquent\ReturnPolicyRepository;
use App\Modules\AfterSales\Repositories\Eloquent\ReturnRequestRepository;
use App\Modules\AfterSales\Support\GatewayRefundWebhooks;
use App\Modules\AfterSales\Support\RefundLedger;
use App\Modules\AfterSales\Support\ReturnsBadgeCounter;
use App\Modules\Commerce\Contracts\GatewayRefundUpdates;
use App\Modules\Commerce\Contracts\OrderRefundLedger;
use App\Modules\Commerce\Events\OrderCancelled;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Shared\Navigation\Contracts\PanelBadgeCounter;
use Illuminate\Support\Facades\Event;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        ReturnPolicyRepositoryContract::class => ReturnPolicyRepository::class,
        ReturnRequestRepositoryContract::class => ReturnRequestRepository::class,
        RefundRepositoryContract::class => RefundRepository::class,
        OrderRefundLedger::class => RefundLedger::class,
        GatewayRefundUpdates::class => GatewayRefundWebhooks::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->tag([ReturnsBadgeCounter::class], PanelBadgeCounter::TAG);
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(OrderCancelled::class, RefundCancelledOrder::class);
        Event::listen(RefundCompleted::class, RequestAffiliateReversal::class);
        Event::subscribe(SendAfterSalesNotifications::class);
    }
}
