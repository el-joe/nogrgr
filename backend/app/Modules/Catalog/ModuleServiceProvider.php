<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Jobs\RefreshBadgeAutoFill;
use App\Modules\Catalog\Repositories\Contracts\AttributeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\CategoryRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductBadgeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductLabelRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Modules\Catalog\Repositories\Eloquent\AttributeRepository;
use App\Modules\Catalog\Repositories\Eloquent\CategoryRepository;
use App\Modules\Catalog\Repositories\Eloquent\ProductBadgeRepository;
use App\Modules\Catalog\Repositories\Eloquent\ProductLabelRepository;
use App\Modules\Catalog\Repositories\Eloquent\ProductRepository;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

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
    ];

    public function boot(): void
    {
        parent::boot();

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->job(new RefreshBadgeAutoFill)->dailyAt(self::BADGE_REFRESH_AT)->name('catalog:refresh-badge-auto-fill')->onOneServer();
        });
    }
}
