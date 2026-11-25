<?php

namespace App\Modules\Billing;

use App\Modules\Billing\Events\InvoicePaid;
use App\Modules\Billing\Listeners\SendBillingNotifications;
use App\Modules\Billing\Listeners\SettleInvoiceBillable;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Repositories\Contracts\AddonPurchaseRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\InvoiceRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\PaymentLogRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Modules\Billing\Repositories\Eloquent\AddonPurchaseRepository;
use App\Modules\Billing\Repositories\Eloquent\InvoiceRepository;
use App\Modules\Billing\Repositories\Eloquent\PaymentLogRepository;
use App\Modules\Billing\Repositories\Eloquent\PlanRepository;
use App\Modules\Billing\Repositories\Eloquent\SubscriptionRepository;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        AddonPurchaseRepositoryContract::class => AddonPurchaseRepository::class,
        PlanRepositoryContract::class => PlanRepository::class,
        SubscriptionRepositoryContract::class => SubscriptionRepository::class,
        InvoiceRepositoryContract::class => InvoiceRepository::class,
        PaymentLogRepositoryContract::class => PaymentLogRepository::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // Stable invoice "billable" types (never class names in the DB).
        Relation::morphMap([
            'subscription' => Subscription::class,
            'addon_purchase' => AddonPurchase::class,
        ]);

        Event::listen(InvoicePaid::class, SettleInvoiceBillable::class);
        Event::subscribe(SendBillingNotifications::class);
    }
}
