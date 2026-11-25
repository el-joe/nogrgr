<?php

namespace App\Modules\Reporting\Repositories\Eloquent;

use App\Modules\Reporting\Models\OrderFact;
use App\Modules\Reporting\Repositories\Contracts\OrderFactRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<OrderFact>
 */
class OrderFactRepository extends BaseRepository implements OrderFactRepositoryContract
{
    protected function model(): string
    {
        return OrderFact::class;
    }

    public function upsertFor(int $tenantId, string $orderUuid, array $attributes): OrderFact
    {
        /** @var OrderFact $fact */
        $fact = OrderFact::withoutTenantScope()->updateOrCreate(
            ['tenant_id' => $tenantId, 'order_uuid' => $orderUuid],
            $attributes,
        );

        return $fact;
    }
}
