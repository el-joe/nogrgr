<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Models\OrderPayment;
use App\Modules\Commerce\Models\PaymentWebhookEvent;
use App\Modules\Commerce\Repositories\Contracts\OrderPaymentRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * @extends BaseRepository<OrderPayment>
 */
class OrderPaymentRepository extends BaseRepository implements OrderPaymentRepositoryContract
{
    protected function model(): string
    {
        return OrderPayment::class;
    }

    public function findByReference(string $reference): ?OrderPayment
    {
        return $this->query()->where('reference', $reference)->first();
    }

    public function findByCheckoutId(string $gateway, string $checkoutId): ?OrderPayment
    {
        return $this->query()->where('gateway', $gateway)->where('checkout_id', $checkoutId)->first();
    }

    public function forGroup(string $checkoutGroup): Collection
    {
        return $this->query()->where('checkout_group', $checkoutGroup)->orderBy('id')->get();
    }

    public function lock(OrderPayment $payment): OrderPayment
    {
        /** @var OrderPayment $locked */
        $locked = $this->query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

        return $locked;
    }

    public function rememberWebhook(string $gateway, string $eventId, string $type, ?string $checkoutId): bool
    {
        try {
            PaymentWebhookEvent::query()->create([
                'gateway' => $gateway,
                'event_id' => $eventId,
                'type' => $type,
                'checkout_id' => $checkoutId,
                'processed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
