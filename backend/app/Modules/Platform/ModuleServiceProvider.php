<?php

namespace App\Modules\Platform;

use App\Modules\Platform\Repositories\Contracts\AuditEventRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Repositories\Eloquent\AuditEventRepository;
use App\Modules\Platform\Repositories\Eloquent\TenantRepository;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        AuditEventRepositoryContract::class => AuditEventRepository::class,
        TenantRepositoryContract::class => TenantRepository::class,
    ];
}
