<?php

namespace App\Modules\Inventory\Repositories\Contracts;

use App\Modules\Inventory\Models\StockAlertSubscription;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Back-in-stock subscriptions (tenant DB).
 *
 * @extends RepositoryContract<StockAlertSubscription>
 */
interface StockAlertSubscriptionRepositoryContract extends RepositoryContract
{
    /**
     * Creates or re-arms (notified_at = null) the subscription of an e-mail.
     */
    public function subscribe(int $storeProductId, string $email, ?int $customerId, ?string $locale): StockAlertSubscription;

    /**
     * Subscriptions still waiting for the product, oldest first.
     *
     * @return Collection<int, StockAlertSubscription>
     */
    public function pending(int $storeProductId): Collection;

    /**
     * Marks pending rows as notified; returns how many this call claimed (0 = another
     * worker already sent them).
     *
     * @param  list<int>  $ids
     */
    public function claim(array $ids): int;

    public function isPending(int $storeProductId, string $email): bool;
}
