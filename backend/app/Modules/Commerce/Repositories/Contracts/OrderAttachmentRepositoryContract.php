<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Commerce\Models\OrderAttachment;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<OrderAttachment>
 */
interface OrderAttachmentRepositoryContract extends RepositoryContract
{
    /**
     * @return Collection<int, OrderAttachment>
     */
    public function forOrder(int $tenantId, string $orderUuid): Collection;

    public function findForOrder(int $tenantId, string $orderUuid, int $id): ?OrderAttachment;
}
