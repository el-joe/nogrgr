<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderStatusHistory;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Order>
 */
class OrderRepository extends BaseRepository implements OrderRepositoryContract
{
    protected function model(): string
    {
        return Order::class;
    }

    public function createWithItems(array $attributes, array $items): Order
    {
        /** @var Order $order */
        $order = $this->query()->create($attributes);
        $order->items()->createMany($items);

        return $order->load('items');
    }

    public function findByUuid(string $uuid): ?Order
    {
        return $this->query()->where('uuid', $uuid)->first();
    }

    public function forGroup(string $checkoutGroup): Collection
    {
        return $this->query()->where('checkout_group', $checkoutGroup)->orderBy('id')->get();
    }

    public function forCustomer(int $customerId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $this->query()
            ->where('customer_id', $customerId)
            ->when(($filters['status'] ?? null) !== null, static fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($search !== '', static fn (Builder $query) => $query->where(static fn (Builder $inner) => $inner
                ->where('number', 'like', '%'.$search.'%')
                ->orWhere('tracking_number', 'like', '%'.$search.'%')
                ->orWhereHas('items', static fn (Builder $items) => $items->where('name', 'like', '%'.$search.'%'))))
            ->with('items')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function countForCustomer(int $customerId): int
    {
        return $this->query()->where('customer_id', $customerId)->count();
    }

    public function nextNumber(): string
    {
        $last = (int) $this->query()->max('id');

        return 'ORD-'.str_pad((string) (10001 + $last), 5, '0', STR_PAD_LEFT);
    }

    public function expiredPendingGroups(int $limit = 100): array
    {
        return $this->query()
            ->where('status', OrderStatus::PendingPayment)
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<', now())
            ->distinct()
            ->limit($limit)
            ->pluck('checkout_group')
            ->map(static fn (mixed $group): string => (string) $group)
            ->all();
    }

    public function recordHistory(Order $order, ?OrderStatus $from, OrderStatus $to, ActorType $actor, ?int $actorId, ?string $note): void
    {
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actor,
            'actor_id' => $actorId,
            'note' => $note,
        ]);
    }
}
