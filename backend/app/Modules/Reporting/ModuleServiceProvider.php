<?php

namespace App\Modules\Reporting;

use App\Modules\Commerce\Events\CartItemAdded;
use App\Modules\Commerce\Events\OrderFulfilmentUpdated;
use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Reporting\Listeners\RecordOrderFacts;
use App\Modules\Reporting\Listeners\RecordProductActivity;
use App\Modules\Reporting\Repositories\Contracts\AffiliateReportRepositoryContract;
use App\Modules\Reporting\Repositories\Contracts\AfterSalesReportRepositoryContract;
use App\Modules\Reporting\Repositories\Contracts\OrderFactRepositoryContract;
use App\Modules\Reporting\Repositories\Contracts\ProductActivityRepositoryContract;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Repositories\Contracts\SubscriptionReportRepositoryContract;
use App\Modules\Reporting\Repositories\Eloquent\AffiliateReportRepository;
use App\Modules\Reporting\Repositories\Eloquent\AfterSalesReportRepository;
use App\Modules\Reporting\Repositories\Eloquent\OrderFactRepository;
use App\Modules\Reporting\Repositories\Eloquent\ProductActivityRepository;
use App\Modules\Reporting\Repositories\Eloquent\ReportFactRepository;
use App\Modules\Reporting\Repositories\Eloquent\SubscriptionReportRepository;
use App\Modules\Storefront\Events\ProductViewed;
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
        OrderFactRepositoryContract::class => OrderFactRepository::class,
        ReportFactRepositoryContract::class => ReportFactRepository::class,
        AfterSalesReportRepositoryContract::class => AfterSalesReportRepository::class,
        SubscriptionReportRepositoryContract::class => SubscriptionReportRepository::class,
        AffiliateReportRepositoryContract::class => AffiliateReportRepository::class,
        ProductActivityRepositoryContract::class => ProductActivityRepository::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Event::listen(OrderStatusChanged::class, RecordOrderFacts::class);
        Event::listen(OrderFulfilmentUpdated::class, RecordOrderFacts::class);
        Event::listen(ProductViewed::class, RecordProductActivity::class);
        Event::listen(CartItemAdded::class, RecordProductActivity::class);
        Event::listen(OrderPaid::class, RecordProductActivity::class);
    }
}
