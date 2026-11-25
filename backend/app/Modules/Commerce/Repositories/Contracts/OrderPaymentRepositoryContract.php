<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Commerce\Models\OrderPayment;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<OrderPayment>
 */
interface OrderPaymentRepositoryContract extends RepositoryContract
{
    public function findByReference(string $reference): ?OrderPayment;

    public function findByCheckoutId(string $gateway, string $checkoutId): ?OrderPayment;

    /**
     * @return Collection<int, OrderPayment>
     */
    public function forGroup(string $checkoutGroup): Collection;

    /**
     * Row-locked re-read inside a transaction.
     */
    public function lock(OrderPayment $payment): OrderPayment;

    /**
     * Stores a webhook event id; false when it was already processed (replay).
     */
    public function rememberWebhook(string $gateway, string $eventId, string $type, ?string $checkoutId): bool;

    /**
     * The captured payment of a checkout group (refunds go back to it).
     */
    public function paidForGroup(string $checkoutGroup): ?OrderPayment;
}
