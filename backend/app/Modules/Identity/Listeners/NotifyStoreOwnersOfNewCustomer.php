<?php

namespace App\Modules\Identity\Listeners;

use App\Modules\Identity\Events\CustomerRegistered;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Notifications\NewCustomerRegisteredNotification;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use Illuminate\Support\Facades\Notification;

/**
 * Writes the "new customer registered" notification for the store's active owners.
 */
class NotifyStoreOwnersOfNewCustomer
{
    public function __construct(
        private readonly CustomerRepositoryContract $customers,
        private readonly VendorStaffRepositoryContract $staff,
    ) {}

    public function handle(CustomerRegistered $event): void
    {
        if (! tenancy()->initialized || (int) tenant()?->getTenantKey() !== $event->tenantId) {
            return;
        }

        $customer = $this->customers->find($event->customerId);

        if (! $customer instanceof Customer) {
            return;
        }

        $owners = $this->staff->activeOwners();

        if ($owners->isNotEmpty()) {
            Notification::send($owners, NewCustomerRegisteredNotification::for($customer));
        }
    }
}
