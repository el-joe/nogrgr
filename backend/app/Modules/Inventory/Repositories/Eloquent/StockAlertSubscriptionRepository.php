<?php

namespace App\Modules\Inventory\Repositories\Eloquent;

use App\Modules\Inventory\Models\StockAlertSubscription;
use App\Modules\Inventory\Repositories\Contracts\StockAlertSubscriptionRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<StockAlertSubscription>
 */
class StockAlertSubscriptionRepository extends BaseRepository implements StockAlertSubscriptionRepositoryContract
{
    protected function model(): string
    {
        return StockAlertSubscription::class;
    }

    public function subscribe(int $storeProductId, string $email, ?int $customerId, ?string $locale): StockAlertSubscription
    {
        /** @var StockAlertSubscription $subscription */
        $subscription = $this->query()->updateOrCreate(
            ['store_product_id' => $storeProductId, 'email' => $email],
            ['customer_id' => $customerId, 'locale' => $locale, 'notified_at' => null],
        );

        return $subscription;
    }

    public function pending(int $storeProductId): Collection
    {
        return $this->query()->where('store_product_id', $storeProductId)->whereNull('notified_at')->orderBy('id')->get();
    }

    public function claim(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return $this->query()->whereKey($ids)->whereNull('notified_at')->update(['notified_at' => now()]);
    }

    public function isPending(int $storeProductId, string $email): bool
    {
        return $this->query()->where('store_product_id', $storeProductId)->where('email', $email)->whereNull('notified_at')->exists();
    }
}
