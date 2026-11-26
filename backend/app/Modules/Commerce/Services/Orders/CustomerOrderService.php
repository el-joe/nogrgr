<?php

namespace App\Modules\Commerce\Services\Orders;

use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Identity\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The signed-in customer's orders (account history + detail): only their own orders of
 * the current store; anything else is "not found".
 */
class CustomerOrderService
{
    public function __construct(private readonly OrderRepositoryContract $orders) {}

    /**
     * @param  array{status?: string|list<string>|null, search?: string|null}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function history(Customer $customer, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->orders->forCustomer($customer->id, $filters, $perPage);
    }

    public function find(Customer $customer, string $uuid): Order
    {
        $order = $this->orders->findByUuid($uuid);

        if ($order === null || $order->customer_id !== $customer->id) {
            throw CommerceException::rule('order_not_found', 404);
        }

        return $order->load(['items', 'history']);
    }
}
