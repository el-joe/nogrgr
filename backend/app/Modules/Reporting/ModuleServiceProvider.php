<?php

namespace App\Modules\Reporting;

use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Reporting\Listeners\RecordOrderFacts;
use App\Modules\Reporting\Repositories\Contracts\OrderFactRepositoryContract;
use App\Modules\Reporting\Repositories\Eloquent\OrderFactRepository;
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
    ];

    public function boot(): void
    {
        parent::boot();

        Event::listen(OrderStatusChanged::class, RecordOrderFacts::class);
    }
}
