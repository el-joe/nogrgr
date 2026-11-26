<?php

namespace App\Modules\Notifications\Repositories\Contracts;

use App\Modules\Notifications\Models\CustomerDevice;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tenant DB (current store).
 *
 * @extends RepositoryContract<CustomerDevice>
 */
interface CustomerDeviceRepositoryContract extends RepositoryContract
{
    public function findByDeviceId(string $deviceId): ?CustomerDevice;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertByDeviceId(string $deviceId, array $attributes): CustomerDevice;

    /**
     * Installs of the customer that can receive push (token set, push on).
     *
     * @return Collection<int, CustomerDevice>
     */
    public function pushTargets(int $customerId): Collection;

    /**
     * @return Collection<int, CustomerDevice>
     */
    public function forCustomer(int $customerId): Collection;

    public function clearToken(string $pushToken): void;

    public function detachCustomer(int $customerId, ?string $deviceId = null): void;
}
