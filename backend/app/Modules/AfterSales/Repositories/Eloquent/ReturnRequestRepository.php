<?php

namespace App\Modules\AfterSales\Repositories\Eloquent;

use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Models\ReturnRequestEvent;
use App\Modules\AfterSales\Models\ReturnRequestMedia;
use App\Modules\AfterSales\Repositories\Contracts\ReturnRequestRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<ReturnRequest>
 */
class ReturnRequestRepository extends BaseRepository implements ReturnRequestRepositoryContract
{
    protected function model(): string
    {
        return ReturnRequest::class;
    }

    public function listing(array $filters): Builder
    {
        $status = ReturnStatus::tryFrom((string) ($filters['status'] ?? ''));
        $tab = is_string($filters['tab'] ?? null) ? $filters['tab'] : '';
        $tenant = filter_var($filters['tenant_id'] ?? null, FILTER_VALIDATE_INT);
        $type = is_string($filters['type'] ?? null) && $filters['type'] !== '' ? $filters['type'] : null;
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';

        return $this->query()
            ->when($status !== null, static fn (Builder $query) => $query->where('status', $status?->value))
            ->when($tab === 'open', static fn (Builder $query) => $query->whereIn('status', ReturnStatus::openValues()))
            ->when($tab !== '' && $tab !== 'open' && ReturnStatus::tryFrom($tab) !== null, static fn (Builder $query) => $query->where('status', $tab))
            ->when(is_int($tenant), static fn (Builder $query) => $query->where('tenant_id', $tenant))
            ->when($type !== null, static fn (Builder $query) => $query->where('type', $type))
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static fn (Builder $inner) => $inner
                    ->where('reference', 'like', $term)
                    ->orWhere('order_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('product_name', 'like', $term));
            });
    }

    public function statusCounts(): array
    {
        /** @var array<string, int> $counts */
        $counts = $this->query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->map(static fn (mixed $n): int => (int) $n)->all();

        return $counts;
    }

    public function findForTenant(int $id, ?int $tenantId = null): ?ReturnRequest
    {
        return $this->query()->whereKey($id)->when($tenantId !== null, static fn (Builder $query) => $query->where('tenant_id', $tenantId))->first();
    }

    public function forCustomer(int $tenantId, int $customerId, ?string $orderUuid, int $perPage): LengthAwarePaginator
    {
        return ReturnRequest::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->when($orderUuid !== null, static fn (Builder $query) => $query->where('order_uuid', $orderUuid))
            ->with(['events' => static fn ($query) => $query->where('customer_visible', true)])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForCustomer(int $tenantId, int $customerId, int $id): ?ReturnRequest
    {
        return ReturnRequest::withoutTenantScope()
            ->whereKey($id)
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->with(['events', 'media', 'refunds'])
            ->first();
    }

    public function forOrder(int $tenantId, string $orderUuid): Collection
    {
        return ReturnRequest::withoutTenantScope()->where('tenant_id', $tenantId)->where('order_uuid', $orderUuid)->orderByDesc('id')->get();
    }

    public function heldQuantity(int $tenantId, string $orderUuid, int $orderItemId): int
    {
        return (int) ReturnRequest::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('order_uuid', $orderUuid)
            ->where('order_item_id', $orderItemId)
            ->whereNotIn('status', [ReturnStatus::Rejected->value, ReturnStatus::Cancelled->value])
            ->sum('quantity');
    }

    public function nextReference(): string
    {
        $last = (int) ReturnRequest::withoutTenantScope()->max('id');

        return 'RMA-'.(1001 + $last);
    }

    public function addEvent(ReturnRequest $return, array $attributes): ReturnRequestEvent
    {
        /** @var ReturnRequestEvent $event */
        $event = $return->events()->create($attributes);

        return $event;
    }

    public function addMedia(ReturnRequest $return, array $attributes): ReturnRequestMedia
    {
        /** @var ReturnRequestMedia $media */
        $media = $return->media()->create($attributes);

        return $media;
    }

    public function findMedia(ReturnRequest $return, int $mediaId): ?ReturnRequestMedia
    {
        return $return->media()->whereKey($mediaId)->first();
    }

    public function openCount(): int
    {
        return $this->query()->whereIn('status', ReturnStatus::openValues())->count();
    }

    public function lock(ReturnRequest $return): ReturnRequest
    {
        /** @var ReturnRequest $locked */
        $locked = ReturnRequest::withoutTenantScope()->whereKey($return->id)->lockForUpdate()->firstOrFail();

        return $locked;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function setStatus(ReturnRequest $return, ReturnStatus $status, array $extra = []): void
    {
        $return->forceFill(['status' => $status] + $extra)->save();
    }
}
