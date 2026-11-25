<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Enums\CustomerNotificationChannel;
use App\Modules\Identity\Enums\CustomerNotificationType;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Shared\Services\BaseService;

/**
 * Customer notification toggles, stored as JSON on `customers.notification_preferences`:
 * `{"order_updates": {"email": true, "push": true}, …}`. Missing entries use
 * CustomerNotificationType::defaultEnabled(). The Notifications module asks allows()
 * before sending mail / push to a customer.
 */
class CustomerNotificationPreferences extends BaseService
{
    public function __construct(private readonly CustomerRepositoryContract $customers) {}

    /**
     * Full matrix: type => channel => enabled.
     *
     * @return array<string, array<string, bool>>
     */
    public function for(Customer $customer): array
    {
        $stored = $customer->notification_preferences ?? [];
        $matrix = [];

        foreach (CustomerNotificationType::cases() as $type) {
            foreach (CustomerNotificationChannel::cases() as $channel) {
                $value = $stored[$type->value][$channel->value] ?? null;
                $matrix[$type->value][$channel->value] = is_bool($value) ? $value : $type->defaultEnabled();
            }
        }

        return $matrix;
    }

    /**
     * @param  array<string, array<string, bool>>  $preferences  type => channel => enabled (partial allowed)
     * @return array<string, array<string, bool>>
     */
    public function update(Customer $customer, array $preferences): array
    {
        $matrix = $this->for($customer);

        foreach ($preferences as $type => $channels) {
            foreach ($channels as $channel => $enabled) {
                if (isset($matrix[$type][$channel])) {
                    $matrix[$type][$channel] = (bool) $enabled;
                }
            }
        }

        $this->customers->update($customer, ['notification_preferences' => $matrix]);

        return $matrix;
    }

    public function allows(Customer $customer, CustomerNotificationType $type, CustomerNotificationChannel $channel): bool
    {
        return $this->for($customer)[$type->value][$channel->value];
    }
}
