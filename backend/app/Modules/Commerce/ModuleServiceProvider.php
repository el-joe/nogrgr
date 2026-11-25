<?php

namespace App\Modules\Commerce;

use App\Modules\Commerce\Jobs\ExpireUnpaidOrders;
use App\Modules\Commerce\Listeners\RecordSettlementPayment;
use App\Modules\Commerce\Listeners\SendOrderNotifications;
use App\Modules\Commerce\Repositories\Contracts\CartRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\CheckoutAttemptRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\CouponRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\FlashSaleRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\HiddenPromotionRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\OrderAdminNoteRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\OrderAttachmentRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\OrderPaymentRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\OrderSettlementRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\StockReservationRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\StoreCouponRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\StoreFlashSaleRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\StorePaymentGatewayRepositoryContract;
use App\Modules\Commerce\Repositories\Eloquent\CartRepository;
use App\Modules\Commerce\Repositories\Eloquent\CheckoutAttemptRepository;
use App\Modules\Commerce\Repositories\Eloquent\CouponRepository;
use App\Modules\Commerce\Repositories\Eloquent\FlashSaleRepository;
use App\Modules\Commerce\Repositories\Eloquent\HiddenPromotionRepository;
use App\Modules\Commerce\Repositories\Eloquent\OrderAdminNoteRepository;
use App\Modules\Commerce\Repositories\Eloquent\OrderAttachmentRepository;
use App\Modules\Commerce\Repositories\Eloquent\OrderPaymentRepository;
use App\Modules\Commerce\Repositories\Eloquent\OrderRepository;
use App\Modules\Commerce\Repositories\Eloquent\OrderSettlementRepository;
use App\Modules\Commerce\Repositories\Eloquent\StockReservationRepository;
use App\Modules\Commerce\Repositories\Eloquent\StoreCouponRepository;
use App\Modules\Commerce\Repositories\Eloquent\StoreFlashSaleRepository;
use App\Modules\Commerce\Repositories\Eloquent\StorePaymentGatewayRepository;
use App\Modules\Commerce\Support\MoneyPresenter;
use App\Modules\Commerce\Support\TenantRunner;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

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
        CartRepositoryContract::class => CartRepository::class,
        OrderRepositoryContract::class => OrderRepository::class,
        OrderPaymentRepositoryContract::class => OrderPaymentRepository::class,
        CheckoutAttemptRepositoryContract::class => CheckoutAttemptRepository::class,
        StorePaymentGatewayRepositoryContract::class => StorePaymentGatewayRepository::class,
        StockReservationRepositoryContract::class => StockReservationRepository::class,
        OrderSettlementRepositoryContract::class => OrderSettlementRepository::class,
        OrderAttachmentRepositoryContract::class => OrderAttachmentRepository::class,
        OrderAdminNoteRepositoryContract::class => OrderAdminNoteRepository::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->scoped(MoneyPresenter::class);
        $this->app->singleton(TenantRunner::class);
    }

    public function boot(): void
    {
        parent::boot();

        Event::subscribe(SendOrderNotifications::class);
        Event::subscribe(RecordSettlementPayment::class);

        // Payment timeout: unpaid orders past their reservation are cancelled, stock released.
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->job(new ExpireUnpaidOrders)->everyFiveMinutes()->name('commerce:expire-unpaid-orders')->withoutOverlapping()->onOneServer();
        });
    }
}
