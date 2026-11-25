<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Models\OrderAttachment;
use App\Modules\Commerce\Repositories\Contracts\OrderAttachmentRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<OrderAttachment>
 */
class OrderAttachmentRepository extends BaseRepository implements OrderAttachmentRepositoryContract
{
    protected function model(): string
    {
        return OrderAttachment::class;
    }

    public function forOrder(int $tenantId, string $orderUuid): Collection
    {
        return OrderAttachment::withoutTenantScope()->where('tenant_id', $tenantId)->where('order_uuid', $orderUuid)->orderByDesc('id')->get();
    }

    public function findForOrder(int $tenantId, string $orderUuid, int $id): ?OrderAttachment
    {
        return OrderAttachment::withoutTenantScope()->where('tenant_id', $tenantId)->where('order_uuid', $orderUuid)->whereKey($id)->first();
    }
}
