<?php

namespace App\Modules\Identity\Repositories\Eloquent;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Enums\CustomerSegment;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\VendorCustomerRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Customer>
 */
class VendorCustomerRepository extends BaseRepository implements VendorCustomerRepositoryContract
{
    protected function model(): string
    {
        return Customer::class;
    }

    public function listing(array $filters): Builder
    {
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        $status = AccountStatus::tryFrom(is_string($filters['status'] ?? null) ? $filters['status'] : '');
        $segment = CustomerSegment::tryFrom(is_string($filters['segment'] ?? null) ? $filters['segment'] : '');
        $from = is_string($filters['from'] ?? null) && strtotime($filters['from']) !== false ? $filters['from'] : null;
        $to = is_string($filters['to'] ?? null) && strtotime($filters['to']) !== false ? $filters['to'] : null;

        return $this->query()
            ->select('customers.*')
            ->selectSub($this->placedOrders()->selectRaw('count(*)'), 'orders_count')
            ->selectSub($this->spendingOrders()->selectRaw('coalesce(sum(grand_total), 0)'), 'lifetime_minor')
            ->selectSub($this->placedOrders()->selectRaw('max(placed_at)'), 'last_order_at')
            ->when($status !== null, static fn (Builder $query) => $query->where('status', $status?->value))
            ->when($segment === CustomerSegment::Buyers, fn (Builder $query) => $query->whereExists($this->placedOrders()->getQuery()))
            ->when($segment === CustomerSegment::NoOrders, fn (Builder $query) => $query->whereNotExists($this->placedOrders()->getQuery()))
            ->when($from !== null, static fn (Builder $query) => $query->whereDate('created_at', '>=', (string) $from))
            ->when($to !== null, static fn (Builder $query) => $query->whereDate('created_at', '<=', (string) $to))
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $id = ltrim($search, '#');
                $query->where(static fn (Builder $inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->when(ctype_digit($id), static fn (Builder $byId) => $byId->orWhere('id', (int) $id)));
            });
    }

    public function stats(): array
    {
        $buyers = $this->query()->whereExists($this->placedOrders()->getQuery());

        return [
            'customers' => $this->query()->count(),
            'active' => $this->query()->where('status', AccountStatus::Active->value)->count(),
            'buyers' => $buyers->count(),
            'lifetime_minor' => (int) Order::query()
                ->whereIn('customer_id', $this->query()->select('id'))
                ->whereNotIn('status', self::notSpending())
                ->sum('grand_total'),
        ];
    }

    public function orderFigures(int $customerId): array
    {
        $placed = Order::query()->where('customer_id', $customerId)->where('status', '!=', OrderStatus::PendingPayment->value);
        $last = (clone $placed)->max('placed_at');

        return [
            'orders' => (clone $placed)->count(),
            'lifetime_minor' => (int) Order::query()->where('customer_id', $customerId)->whereNotIn('status', self::notSpending())->sum('grand_total'),
            'last_order_at' => is_string($last) ? $last : null,
        ];
    }

    /**
     * Orders of the outer `customers` row that were placed (pending-payment checkouts excluded).
     *
     * @return Builder<Order>
     */
    private function placedOrders(): Builder
    {
        return Order::query()
            ->whereColumn('orders.customer_id', 'customers.id')
            ->where('orders.status', '!=', OrderStatus::PendingPayment->value);
    }

    /**
     * @return Builder<Order>
     */
    private function spendingOrders(): Builder
    {
        return Order::query()
            ->whereColumn('orders.customer_id', 'customers.id')
            ->whereNotIn('orders.status', self::notSpending());
    }

    /**
     * Orders that do not count towards what a customer spent.
     *
     * @return list<string>
     */
    private static function notSpending(): array
    {
        return [OrderStatus::PendingPayment->value, OrderStatus::Cancelled->value, OrderStatus::Refunded->value];
    }
}
