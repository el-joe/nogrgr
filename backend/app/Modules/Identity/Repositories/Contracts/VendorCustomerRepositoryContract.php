<?php

namespace App\Modules\Identity\Repositories\Contracts;

use App\Modules\Identity\Models\Customer;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vendor › Customers (VEN-025 / 025b): the store's customers with their order figures.
 * Tenant-DB repository: call inside tenant context.
 *
 * Order figures: `orders_count` / `last_order_at` count every placed order (not the
 * pending-payment checkouts), `lifetime_minor` sums the grand total (USD minor units)
 * of orders that were not cancelled or refunded.
 *
 * @extends RepositoryContract<Customer>
 */
interface VendorCustomerRepositoryContract extends RepositoryContract
{
    /**
     * Customers with orders_count, lifetime_minor and last_order_at, filtered by
     * search (id, name, e-mail, phone), status, segment and joined from / to.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Customer>
     */
    public function listing(array $filters): Builder;

    /**
     * @return array{customers: int, active: int, buyers: int, lifetime_minor: int}
     */
    public function stats(): array;

    /**
     * One customer's order figures.
     *
     * @return array{orders: int, lifetime_minor: int, last_order_at: ?string}
     */
    public function orderFigures(int $customerId): array;
}
