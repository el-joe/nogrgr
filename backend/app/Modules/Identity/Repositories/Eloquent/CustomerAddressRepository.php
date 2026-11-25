<?php

namespace App\Modules\Identity\Repositories\Eloquent;

use App\Modules\Identity\Models\CustomerAddress;
use App\Modules\Identity\Repositories\Contracts\CustomerAddressRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<CustomerAddress>
 */
class CustomerAddressRepository extends BaseRepository implements CustomerAddressRepositoryContract
{
    protected function model(): string
    {
        return CustomerAddress::class;
    }

    public function forCustomer(int $customerId): Collection
    {
        return $this->query()
            ->with('country')
            ->where('customer_id', $customerId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    public function findForCustomer(int $customerId, int $addressId): ?CustomerAddress
    {
        return $this->query()->with('country')->where('customer_id', $customerId)->whereKey($addressId)->first();
    }

    public function defaultFor(int $customerId): ?CustomerAddress
    {
        return $this->query()->with('country')->where('customer_id', $customerId)->where('is_default', true)->orderBy('id')->first();
    }

    public function oldestFor(int $customerId): ?CustomerAddress
    {
        return $this->query()->with('country')->where('customer_id', $customerId)->orderBy('id')->first();
    }

    public function countFor(int $customerId): int
    {
        return $this->query()->where('customer_id', $customerId)->count();
    }

    public function lockForCustomer(int $customerId): void
    {
        $this->query()->where('customer_id', $customerId)->lockForUpdate()->pluck('id');
    }

    public function clearDefault(int $customerId, ?int $exceptId = null): int
    {
        return $this->query()
            ->where('customer_id', $customerId)
            ->where('is_default', true)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->update(['is_default' => false]);
    }

    public function deleteForCustomer(int $customerId): int
    {
        return (int) $this->query()->where('customer_id', $customerId)->delete();
    }
}
