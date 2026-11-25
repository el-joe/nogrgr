<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<Order>
 */
interface OrderRepositoryContract extends RepositoryContract
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $items
     */
    public function createWithItems(array $attributes, array $items): Order;

    public function findByUuid(string $uuid): ?Order;

    /**
     * @return Collection<int, Order>
     */
    public function forGroup(string $checkoutGroup): Collection;

    /**
     * @param  array{status?: string|null, search?: string|null}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function forCustomer(int $customerId, array $filters, int $perPage = 15): LengthAwarePaginator;

    public function countForCustomer(int $customerId): int;

    public function nextNumber(): string;

    /**
     * Groups whose unpaid orders passed their reservation deadline.
     *
     * @return list<string>
     */
    public function expiredPendingGroups(int $limit = 100): array;

    public function recordHistory(Order $order, ?OrderStatus $from, OrderStatus $to, ActorType $actor, ?int $actorId, ?string $note): void;
}
