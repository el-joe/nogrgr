<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Models\OrderAdminNote;
use App\Modules\Commerce\Repositories\Contracts\OrderAdminNoteRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<OrderAdminNote>
 */
class OrderAdminNoteRepository extends BaseRepository implements OrderAdminNoteRepositoryContract
{
    protected function model(): string
    {
        return OrderAdminNote::class;
    }

    public function forOrder(int $tenantId, string $orderUuid): Collection
    {
        return OrderAdminNote::withoutTenantScope()->where('tenant_id', $tenantId)->where('order_uuid', $orderUuid)->orderByDesc('id')->get();
    }
}
