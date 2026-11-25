<?php

namespace App\Modules\Catalog;

use App\Modules\Billing\Events\InvoicePaid;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Events\CatalogImportFinished;
use App\Modules\Catalog\Jobs\RefreshBadgeAutoFill;
use App\Modules\Catalog\Listeners\NotifyCatalogImportFinished;
use App\Modules\Catalog\Listeners\Requests\SendRequestNotifications;
use App\Modules\Catalog\Listeners\Requests\SettleRequestPayment;
use App\Modules\Catalog\Models\Requests\BrandRequest;
use App\Modules\Catalog\Models\Requests\ManufacturingRequest;
use App\Modules\Catalog\Models\Requests\ProductRequest;
use App\Modules\Catalog\Models\Requests\RequestAttachment;
use App\Modules\Catalog\Models\Requests\RequestPayment;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Policies\RequestAttachmentPolicy;
use App\Modules\Catalog\Policies\StoreProductPolicy;
use App\Modules\Catalog\Repositories\Contracts\AttributeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\BrandRequestRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\CatalogImportRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\CategoryRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\EditRequestRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ManufacturingRequestRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductBadgeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductLabelRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductOverrideRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductRequestRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\RequestMessageRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\RequestPaymentRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\StorePriceListRunRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\TenantChangeRequestRepositoryContract;
use App\Modules\Catalog\Repositories\Eloquent\AttributeRepository;
use App\Modules\Catalog\Repositories\Eloquent\BrandRequestRepository;
use App\Modules\Catalog\Repositories\Eloquent\CatalogImportRepository;
use App\Modules\Catalog\Repositories\Eloquent\CategoryRepository;
use App\Modules\Catalog\Repositories\Eloquent\EditRequestRepository;
use App\Modules\Catalog\Repositories\Eloquent\ManufacturingRequestRepository;
use App\Modules\Catalog\Repositories\Eloquent\ProductBadgeRepository;
use App\Modules\Catalog\Repositories\Eloquent\ProductLabelRepository;
use App\Modules\Catalog\Repositories\Eloquent\ProductOverrideRepository;
use App\Modules\Catalog\Repositories\Eloquent\ProductRepository;
use App\Modules\Catalog\Repositories\Eloquent\ProductRequestRepository;
use App\Modules\Catalog\Repositories\Eloquent\RequestMessageRepository;
use App\Modules\Catalog\Repositories\Eloquent\RequestPaymentRepository;
use App\Modules\Catalog\Repositories\Eloquent\StorePriceListRunRepository;
use App\Modules\Catalog\Repositories\Eloquent\StoreProductRepository;
use App\Modules\Catalog\Repositories\Eloquent\TenantChangeRequestRepository;
use App\Modules\Catalog\Support\RequestsBadgeCounter;
use App\Modules\Identity\Models\Admin;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Shared\Navigation\Contracts\PanelBadgeCounter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Time of the daily refill of automatic badge lists.
     */
    public const BADGE_REFRESH_AT = '03:30';

    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        CategoryRepositoryContract::class => CategoryRepository::class,
        AttributeRepositoryContract::class => AttributeRepository::class,
        ProductLabelRepositoryContract::class => ProductLabelRepository::class,
        ProductBadgeRepositoryContract::class => ProductBadgeRepository::class,
        ProductRepositoryContract::class => ProductRepository::class,
        CatalogImportRepositoryContract::class => CatalogImportRepository::class,
        ProductRequestRepositoryContract::class => ProductRequestRepository::class,
        ManufacturingRequestRepositoryContract::class => ManufacturingRequestRepository::class,
        BrandRequestRepositoryContract::class => BrandRequestRepository::class,
        RequestMessageRepositoryContract::class => RequestMessageRepository::class,
        RequestPaymentRepositoryContract::class => RequestPaymentRepository::class,
        EditRequestRepositoryContract::class => EditRequestRepository::class,
        TenantChangeRequestRepositoryContract::class => TenantChangeRequestRepository::class,
        StoreProductRepositoryContract::class => StoreProductRepository::class,
        StorePriceListRunRepositoryContract::class => StorePriceListRunRepository::class,
        ProductOverrideRepositoryContract::class => ProductOverrideRepository::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->tag([RequestsBadgeCounter::class], PanelBadgeCounter::TAG);
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(CatalogImportFinished::class, NotifyCatalogImportFinished::class);

        $this->bootRequests();

        Gate::policy(StoreProduct::class, StoreProductPolicy::class);

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->job(new RefreshBadgeAutoFill)->dailyAt(self::BADGE_REFRESH_AT)->name('catalog:refresh-badge-auto-fill')->onOneServer();
        });
    }

    /**
     * Requests center: stable morph aliases (never class names in the DB), notifications,
     * invoice settlement, attachment policy and the live thread channel
     * "requests.<kind>.<id>" (admins allowed to view that request type).
     */
    private function bootRequests(): void
    {
        Relation::morphMap([
            RequestKind::Product->morphAlias() => ProductRequest::class,
            RequestKind::Manufacturing->morphAlias() => ManufacturingRequest::class,
            RequestKind::Brand->morphAlias() => BrandRequest::class,
            'request_payment' => RequestPayment::class,
            'store_product' => StoreProduct::class,
        ]);

        Event::subscribe(SendRequestNotifications::class);
        Event::listen(InvoicePaid::class, SettleRequestPayment::class);
        Gate::policy(RequestAttachment::class, RequestAttachmentPolicy::class);

        Broadcast::channel('requests.{kind}.{id}', static function (mixed $user, string $kind, string $id): bool {
            $requestKind = RequestKind::tryFrom($kind);

            return $user instanceof Admin && $requestKind !== null && ctype_digit($id) && $user->can($requestKind->permission('view'));
        }, ['guards' => ['admin']]);
    }
}
