<?php

namespace App\Modules\Identity;

use App\Modules\Identity\Console\SyncPermissionsCommand;
use App\Modules\Identity\Events\AdminAccountCreated;
use App\Modules\Identity\Events\AdminPasswordChanged;
use App\Modules\Identity\Events\AdminRoleChanged;
use App\Modules\Identity\Events\CustomerRegistered;
use App\Modules\Identity\Events\VendorStaffJoined;
use App\Modules\Identity\Listeners\ActivateInvitedVendorStaff;
use App\Modules\Identity\Listeners\NotifyStoreOwnersOfNewCustomer;
use App\Modules\Identity\Listeners\NotifyStoreOwnersOfStaffJoined;
use App\Modules\Identity\Listeners\SendAdminAccountNotifications;
use App\Modules\Identity\Listeners\WelcomeNewCustomer;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Observers\VendorStaffDirectoryObserver;
use App\Modules\Identity\Policies\AdminPolicy;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\CustomerAddressRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\OwnerRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\RoleRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\StoreLoginPasswordResetRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\VendorCustomerRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\VendorStaffDirectoryRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Identity\Repositories\Eloquent\AdminRepository;
use App\Modules\Identity\Repositories\Eloquent\CustomerAddressRepository;
use App\Modules\Identity\Repositories\Eloquent\CustomerRepository;
use App\Modules\Identity\Repositories\Eloquent\OwnerRepository;
use App\Modules\Identity\Repositories\Eloquent\RoleRepository;
use App\Modules\Identity\Repositories\Eloquent\StoreLoginPasswordResetRepository;
use App\Modules\Identity\Repositories\Eloquent\VendorCustomerRepository;
use App\Modules\Identity\Repositories\Eloquent\VendorStaffDirectoryRepository;
use App\Modules\Identity\Repositories\Eloquent\VendorStaffRepository;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Shared\Routing\ModuleRouteRegistry;
use App\Shared\Routing\Surface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        StoreLoginPasswordResetRepositoryContract::class => StoreLoginPasswordResetRepository::class,
        AdminRepositoryContract::class => AdminRepository::class,
        RoleRepositoryContract::class => RoleRepository::class,
        CustomerAddressRepositoryContract::class => CustomerAddressRepository::class,
        CustomerRepositoryContract::class => CustomerRepository::class,
        VendorStaffDirectoryRepositoryContract::class => VendorStaffDirectoryRepository::class,
        VendorStaffRepositoryContract::class => VendorStaffRepository::class,
        OwnerRepositoryContract::class => OwnerRepository::class,
        VendorCustomerRepositoryContract::class => VendorCustomerRepository::class,
    ];

    public function boot(): void
    {
        parent::boot();

        /* Vendor › Customers (VEN-025): its own route file next to routes/vendor.php. */
        $this->app->make(ModuleRouteRegistry::class)->add(Surface::Vendor, $this->modulePath('routes/vendor-customers.php'));

        VendorStaff::observe(VendorStaffDirectoryObserver::class);

        Event::listen(CustomerRegistered::class, NotifyStoreOwnersOfNewCustomer::class);
        Event::listen(CustomerRegistered::class, WelcomeNewCustomer::class);
        Event::listen(PasswordReset::class, ActivateInvitedVendorStaff::class);
        Event::listen(VendorStaffJoined::class, NotifyStoreOwnersOfStaffJoined::class);
        Event::listen(AdminAccountCreated::class, [SendAdminAccountNotifications::class, 'created']);
        Event::listen(AdminPasswordChanged::class, [SendAdminAccountNotifications::class, 'passwordChanged']);
        Event::listen(AdminRoleChanged::class, [SendAdminAccountNotifications::class, 'roleChanged']);

        Gate::policy(Admin::class, AdminPolicy::class);
        Gate::define('manage-own-account', static fn (mixed $user): bool => $user instanceof Admin);

        $this->bootPermissionRules();

        if ($this->app->runningInConsole()) {
            $this->commands([SyncPermissionsCommand::class]);
        }
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
