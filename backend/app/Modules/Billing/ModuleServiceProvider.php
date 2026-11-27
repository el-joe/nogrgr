<?php

namespace App\Modules\Billing;

use App\Modules\Billing\Console\BackfillLedgerCommand;
use App\Modules\Billing\Console\SendRenewalRemindersCommand;
use App\Modules\Billing\Console\SendSettlementDueRemindersCommand;
use App\Modules\Billing\Events\InvoicePaid;
use App\Modules\Billing\Events\PaymentCaptured;
use App\Modules\Billing\Listeners\PostLedgerEntries;
use App\Modules\Billing\Listeners\RenewSubscriptionOnPayment;
use App\Modules\Billing\Listeners\SendBillingNotifications;
use App\Modules\Billing\Listeners\SendGatewayLimitNotifications;
use App\Modules\Billing\Listeners\SendSettlementReceipt;
use App\Modules\Billing\Listeners\SettleInvoiceBillable;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Policies\OwnerBillingPolicy;
use App\Modules\Billing\Repositories\Contracts\AddonPurchaseRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\InvoiceRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\LedgerRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\PaymentLogRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\PlanLimitOverrideRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\TenantPayoutRepositoryContract;
use App\Modules\Billing\Repositories\Eloquent\AddonPurchaseRepository;
use App\Modules\Billing\Repositories\Eloquent\InvoiceRepository;
use App\Modules\Billing\Repositories\Eloquent\LedgerRepository;
use App\Modules\Billing\Repositories\Eloquent\PaymentLogRepository;
use App\Modules\Billing\Repositories\Eloquent\PlanLimitOverrideRepository;
use App\Modules\Billing\Repositories\Eloquent\PlanRepository;
use App\Modules\Billing\Repositories\Eloquent\SubscriptionRepository;
use App\Modules\Billing\Repositories\Eloquent\TenantPayoutRepository;
use App\Modules\Commerce\Events\SettlementPaid;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

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
        PlanLimitOverrideRepositoryContract::class => PlanLimitOverrideRepository::class,
        SubscriptionRepositoryContract::class => SubscriptionRepository::class,
        InvoiceRepositoryContract::class => InvoiceRepository::class,
        PaymentLogRepositoryContract::class => PaymentLogRepository::class,
        LedgerRepositoryContract::class => LedgerRepository::class,
        TenantPayoutRepositoryContract::class => TenantPayoutRepository::class,
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
        Event::subscribe(PostLedgerEntries::class);
        Event::subscribe(SendGatewayLimitNotifications::class);
        Event::listen(PaymentCaptured::class, RenewSubscriptionOnPayment::class);
        Event::listen(SettlementPaid::class, SendSettlementReceipt::class);

        // Owner portal: only subscriptions / invoices of the owner's own stores.
        Gate::define('owner.subscriptions.manage', [OwnerBillingPolicy::class, 'subscription']);
        Gate::define('owner.invoices.view', [OwnerBillingPolicy::class, 'invoice']);

        if ($this->app->runningInConsole()) {
            $this->commands([BackfillLedgerCommand::class, SendRenewalRemindersCommand::class, SendSettlementDueRemindersCommand::class]);
        }

        // Scheduled reminders (decision #124); both are idempotent per period (sent_reminders).
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('billing:renewal-reminders')->dailyAt('09:00')->name('billing:renewal-reminders')->onOneServer()->withoutOverlapping();
            $schedule->command('billing:settlement-reminders')->weeklyOn(1, '10:00')->name('billing:settlement-reminders')->onOneServer()->withoutOverlapping();
        });
    }
}
