<?php

namespace App\Modules\Billing\Repositories\Eloquent;

use App\Modules\Billing\Enums\AddonPurchaseStatus;
use App\Modules\Billing\Enums\AddonType;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Repositories\Contracts\AddonPurchaseRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<AddonPurchase>
 */
class AddonPurchaseRepository extends BaseRepository implements AddonPurchaseRepositoryContract
{
    protected function model(): string
    {
        return AddonPurchase::class;
    }

    public function openFor(int $tenantId, AddonType $type, string $itemCode): ?AddonPurchase
    {
        return AddonPurchase::query()
            ->forTenant($tenantId)
            ->where('type', $type->value)
            ->where('item_code', $itemCode)
            ->where('status', AddonPurchaseStatus::PendingPayment->value)
            ->latest('id')
            ->first();
    }

    public function forTenant(int $tenantId): Collection
    {
        return AddonPurchase::query()->forTenant($tenantId)->latest('id')->get();
    }
}
