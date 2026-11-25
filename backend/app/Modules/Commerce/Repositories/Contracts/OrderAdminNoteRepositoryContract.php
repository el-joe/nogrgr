<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Commerce\Models\OrderAdminNote;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<OrderAdminNote>
 */
interface OrderAdminNoteRepositoryContract extends RepositoryContract
{
    /**
     * @return Collection<int, OrderAdminNote>
     */
    public function forOrder(int $tenantId, string $orderUuid): Collection;
}
