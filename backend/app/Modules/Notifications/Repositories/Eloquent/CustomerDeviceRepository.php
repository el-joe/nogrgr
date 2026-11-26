<?php

namespace App\Modules\Notifications\Repositories\Eloquent;

use App\Modules\Notifications\Models\CustomerDevice;
use App\Modules\Notifications\Repositories\Contracts\CustomerDeviceRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<CustomerDevice>
 */
class CustomerDeviceRepository extends BaseRepository implements CustomerDeviceRepositoryContract
{
    protected function model(): string
    {
        return CustomerDevice::class;
    }

    public function findByDeviceId(string $deviceId): ?CustomerDevice
    {
        return CustomerDevice::query()->where('device_id', $deviceId)->first();
    }

    public function upsertByDeviceId(string $deviceId, array $attributes): CustomerDevice
    {
        return CustomerDevice::query()->updateOrCreate(['device_id' => $deviceId], $attributes);
    }

    public function pushTargets(int $customerId): Collection
    {
        return CustomerDevice::query()
            ->where('customer_id', $customerId)
            ->where('push_enabled', true)
            ->whereNotNull('push_token')
            ->get();
    }

    public function forCustomer(int $customerId): Collection
    {
        return CustomerDevice::query()->where('customer_id', $customerId)->latest('last_seen_at')->get();
    }

    public function clearToken(string $pushToken): void
    {
        CustomerDevice::query()->where('push_token', $pushToken)->update(['push_token' => null]);
    }

    public function detachCustomer(int $customerId, ?string $deviceId = null): void
    {
        CustomerDevice::query()
            ->where('customer_id', $customerId)
            ->when($deviceId !== null, static fn ($query) => $query->where('device_id', $deviceId))
            ->update(['customer_id' => null]);
    }
}
