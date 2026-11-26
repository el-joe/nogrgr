<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Identity\Models\Customer;
use App\Modules\Notifications\Services\CustomerPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pushes one customer inbox row to the customer's app installs (queued; the tenant is
 * restored by the queue tenancy bootstrapper).
 */
class SendCustomerPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $customerId,
        public readonly string $notificationId,
        public readonly array $payload,
    ) {}

    public function handle(CustomerPushService $push): void
    {
        $customer = Customer::query()->find($this->customerId);

        if ($customer instanceof Customer) {
            $push->sendNotification($customer, $this->notificationId, $this->payload);
        }
    }
}
