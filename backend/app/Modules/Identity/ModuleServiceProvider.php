<?php

namespace App\Modules\Identity;

use App\Modules\Identity\Events\CustomerRegistered;
use App\Modules\Identity\Listeners\NotifyStoreOwnersOfNewCustomer;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Observers\VendorStaffDirectoryObserver;
use App\Modules\Identity\Repositories\Contracts\CustomerAddressRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\VendorStaffDirectoryRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Identity\Repositories\Eloquent\CustomerAddressRepository;
use App\Modules\Identity\Repositories\Eloquent\CustomerRepository;
use App\Modules\Identity\Repositories\Eloquent\VendorStaffDirectoryRepository;
use App\Modules\Identity\Repositories\Eloquent\VendorStaffRepository;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use App\Modules\Identity\Models\Admin;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        CustomerAddressRepositoryContract::class => CustomerAddressRepository::class,
        CustomerRepositoryContract::class => CustomerRepository::class,
        VendorStaffDirectoryRepositoryContract::class => VendorStaffDirectoryRepository::class,
        VendorStaffRepositoryContract::class => VendorStaffRepository::class,
    ];

    public function boot(): void
    {
        parent::boot();

        VendorStaff::observe(VendorStaffDirectoryObserver::class);

        Event::listen(CustomerRegistered::class, NotifyStoreOwnersOfNewCustomer::class);

        $this->bootPermissionRules();
    }

    /**
     * Super Admin passes every check; "<resource>.manage" implies "<resource>.view"
     * (route middleware, policies and the sidebar all go through the Gate).
     */
    private function bootPermissionRules(): void
    {
        Gate::before(static function (mixed $user, string $ability): ?bool {
            if ($user instanceof Admin && $user->hasRole('super-admin')) {
                return true;
            }

            if (Str::endsWith($ability, '.view') && is_object($user) && method_exists($user, 'checkPermissionTo')) {
                return $user->checkPermissionTo(Str::beforeLast($ability, '.view').'.manage') ? true : null;
            }

            return null;
        });
    }
}
