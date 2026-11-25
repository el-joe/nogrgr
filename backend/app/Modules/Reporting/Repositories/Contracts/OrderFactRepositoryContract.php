<?php

namespace App\Modules\Reporting\Repositories\Contracts;

use App\Modules\Reporting\Models\OrderFact;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * @extends RepositoryContract<OrderFact>
 */
interface OrderFactRepositoryContract extends RepositoryContract
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertFor(int $tenantId, string $orderUuid, array $attributes): OrderFact;
}
