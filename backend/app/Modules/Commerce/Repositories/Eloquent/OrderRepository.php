<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderStatusHistory;
use App\Modules\Commerce\Models\OrderTrackingEvent;
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
    /** Paid, not void orders (vendor "Collected" KPI). */
    private const COLLECTED_STATUSES = [OrderStatus::Paid->value, OrderStatus::Processing->value, OrderStatus::Shipped->value, OrderStatus::Delivered->value, OrderStatus::Completed->value];

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

    public function recordTrackingEvent(Order $order, FulfilmentStatus $status, ActorType $actor, ?int $actorId, ?string $location, ?string $note): void
    {
        OrderTrackingEvent::query()->create([
            'order_id' => $order->id,
            'status' => $status,
            'location' => $location,
            'note' => $note,
            'actor_type' => $actor,
            'actor_id' => $actorId,
        ]);
    }

    public function listing(array $filters): Builder
    {
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        $status = OrderStatus::tryFrom((string) ($filters['status'] ?? ''));
        $payment = PaymentStatus::tryFrom((string) ($filters['payment'] ?? ''));
        $gateway = is_string($filters['gateway'] ?? null) && $filters['gateway'] !== '' ? $filters['gateway'] : null;
        $kind = is_string($filters['kind'] ?? null) && $filters['kind'] !== '' ? $filters['kind'] : null;
        $from = is_string($filters['from'] ?? null) && strtotime($filters['from']) !== false ? $filters['from'] : null;
        $to = is_string($filters['to'] ?? null) && strtotime($filters['to']) !== false ? $filters['to'] : null;

        return $this->query()
            ->withSum('items as items_quantity', 'quantity')
            ->with(['items' => static fn ($items) => $items->select(['id', 'order_id', 'name', 'image_url'])])
            ->when($status !== null, static fn (Builder $query) => $query->where('status', $status?->value))
            ->when($payment !== null, static fn (Builder $query) => $query->where('payment_status', $payment?->value))
            ->when($gateway !== null, static fn (Builder $query) => $query->where('payment_gateway', $gateway))
            ->when($kind !== null, static fn (Builder $query) => $query->where('kind', $kind))
            ->when($from !== null, static fn (Builder $query) => $query->whereDate('placed_at', '>=', (string) $from))
            ->when($to !== null, static fn (Builder $query) => $query->whereDate('placed_at', '<=', (string) $to))
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static fn (Builder $inner) => $inner
                    ->where('number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term)
                    ->orWhere('tracking_number', 'like', $term)
                    ->orWhereHas('items', static fn (Builder $items) => $items->where('name', 'like', $term)));
            });
    }

    public function collectedUuids(): array
    {
        return array_values(array_map('strval', $this->query()->whereIn('status', self::COLLECTED_STATUSES)->where('payment_status', PaymentStatus::Paid->value)->pluck('uuid')->all()));
    }

    public function stats(): array
    {
        $paid = self::COLLECTED_STATUSES;

        return [
            'orders' => $this->query()->where('status', '!=', OrderStatus::PendingPayment->value)->count(),
            'paid' => $this->query()->where('payment_status', PaymentStatus::Paid->value)->count(),
            'processing' => $this->query()->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Processing->value])->count(),
            'collected' => (int) $this->query()->whereIn('status', $paid)->where('payment_status', PaymentStatus::Paid->value)->sum('grand_total'),
        ];
    }

    public function findDetailed(string $uuid): ?Order
    {
        return $this->query()->where('uuid', $uuid)->with(['items', 'history', 'trackingEvents'])->first();
    }

    public function placedCountForCustomer(int $customerId): int
    {
        return $this->query()->where('customer_id', $customerId)->where('status', '!=', OrderStatus::PendingPayment->value)->count();
    }
}
