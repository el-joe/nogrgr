<?php

namespace App\Modules\Identity\Listeners;

use App\Modules\Identity\Events\CustomerRegistered;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Notifications\CustomerWelcomeNotification;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;

/**
 * MAIL-042: welcome e-mail to the shopper after sign-up (storefront, app, social),
 * inside the store's tenant context so the store template + layout apply.
 */
class WelcomeNewCustomer
{
    public function __construct(private readonly CustomerRepositoryContract $customers) {}

    public function handle(CustomerRegistered $event): void
    {
        $tenant = tenancy()->initialized ? tenant() : null;

        if (! $tenant instanceof Tenant || (int) $tenant->getTenantKey() !== $event->tenantId) {
            return;
        }

        $customer = $this->customers->find($event->customerId);

        if ($customer instanceof Customer && filled($customer->email)) {
            $customer->notify(new CustomerWelcomeNotification((string) $tenant->name, StoreSettingsService::storeUrl($tenant)));
        }
    }
}
