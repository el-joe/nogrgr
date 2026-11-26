<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Identity\Models\Customer;
use App\Modules\Notifications\DTOs\CustomerDeviceData;
use App\Modules\Notifications\Models\CustomerDevice;
use App\Modules\Notifications\Repositories\Contracts\CustomerDeviceRepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * App installs of the store (P11): register / refresh an install and its FCM token (guest
 * or signed-in), bind it to the customer on sign-in, release it on sign-out, remove it.
 * One FCM token belongs to one install only, so a token re-sent by a new install moves.
 */
class CustomerDeviceService
{
    public function __construct(private readonly CustomerDeviceRepositoryContract $devices) {}

    public function register(CustomerDeviceData $data, ?Customer $customer): CustomerDevice
    {
        if ($data->pushToken !== null) {
            $existing = $this->devices->query()->where('push_token', $data->pushToken)->where('device_id', '!=', $data->deviceId)->exists();

            if ($existing) {
                $this->devices->clearToken($data->pushToken);
            }
        }

        $attributes = [
            'platform' => $data->platform,
            'push_token' => $data->pushToken,
            'push_enabled' => $data->pushEnabled,
            'app_version' => $data->appVersion,
            'os_version' => $data->osVersion,
            'model' => $data->model,
            'locale' => $data->locale,
            'last_seen_at' => now(),
        ];

        if ($customer !== null) {
            $attributes['customer_id'] = $customer->id;
        }

        return $this->devices->upsertByDeviceId($data->deviceId, $attributes);
    }

    /**
     * The install now belongs to the signed-in customer (login / register with a device id).
     */
    public function attach(Customer $customer, string $deviceId): void
    {
        $device = $this->devices->findByDeviceId($deviceId);

        if ($device !== null) {
            $this->devices->update($device, ['customer_id' => $customer->id, 'last_seen_at' => now()]);
        }
    }

    /**
     * Sign-out: the install stays registered (guest pushes) but no longer gets the
     * customer's notifications.
     */
    public function detach(Customer $customer, ?string $deviceId): void
    {
        if ($deviceId !== null) {
            $this->devices->detachCustomer($customer->id, $deviceId);
        }
    }

    /**
     * Remove an install of the customer (or a guest install); false when not found.
     */
    public function remove(string $deviceId, ?Customer $customer): bool
    {
        $device = $this->devices->findByDeviceId($deviceId);

        if ($device === null || ($device->customer_id !== null && $device->customer_id !== $customer?->id)) {
            return false;
        }

        $this->devices->delete($device);

        return true;
    }

    /**
     * @return Collection<int, CustomerDevice>
     */
    public function forCustomer(Customer $customer): Collection
    {
        return $this->devices->forCustomer($customer->id);
    }

    public function find(string $deviceId): ?CustomerDevice
    {
        return $this->devices->findByDeviceId($deviceId);
    }
}
