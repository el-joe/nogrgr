<?php

namespace App\Modules\Identity\Notifications;

use App\Modules\Identity\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Store staff: "New customer registered" (vendor panel notification bell). Database
 * channel now; mail / Reverb channels follow from staff preferences once the
 * Notifications module's dispatcher lands (docs/plan/notifications.md).
 */
class NewCustomerRegisteredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $customerId,
        public readonly string $customerName,
    ) {}

    public static function for(Customer $customer): self
    {
        return new self($customer->id, $customer->name);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{type: string, customer_id: int, customer_name: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'customer.registered',
            'customer_id' => $this->customerId,
            'customer_name' => $this->customerName,
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return 'customer.registered';
    }
}
