<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\CheckoutAddressSelectionData;
use App\Modules\Identity\DTOs\CustomerAddressData;
use App\Modules\Identity\DTOs\OrderAddressSnapshotData;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\CustomerAddress;
use App\Modules\Identity\Repositories\Contracts\CustomerAddressRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Saved customer addresses (spec §1–§2), shared by the storefront account page,
 * checkout, the vendor panel and the mobile API. Tenant context required.
 *
 * Default rules:
 *  - the first address of a customer is the default automatically;
 *  - making an address default un-defaults all others (exactly one default);
 *  - deleting the default promotes the oldest remaining address.
 */
class CustomerAddressService extends BaseService
{
    public function __construct(private readonly CustomerAddressRepositoryContract $addresses) {}

    /**
     * @return Collection<int, CustomerAddress>
     */
    public function list(Customer $customer): Collection
    {
        return $this->addresses->forCustomer($customer->id);
    }

    /**
     * @throws ModelNotFoundException when the address is not the customer's
     */
    public function findOrFail(Customer $customer, int $addressId): CustomerAddress
    {
        return $this->addresses->findForCustomer($customer->id, $addressId)
            ?? throw (new ModelNotFoundException)->setModel(CustomerAddress::class, [$addressId]);
    }

    public function create(Customer $customer, CustomerAddressData $data): CustomerAddress
    {
        $address = $this->transaction(function () use ($customer, $data): CustomerAddress {
            $this->addresses->lockForCustomer($customer->id);

            $makeDefault = $data->isDefault || $this->addresses->countFor($customer->id) === 0;

            /** @var CustomerAddress $address */
            $address = $this->addresses->create([
                ...$data->attributes(),
                'customer_id' => $customer->id,
                'is_default' => $makeDefault,
            ]);

            if ($makeDefault) {
                $this->addresses->clearDefault($customer->id, $address->id);
            }

            return $address;
        });

        return $this->findOrFail($customer, $address->id);
    }

    public function update(Customer $customer, CustomerAddress $address, CustomerAddressData $data): CustomerAddress
    {
        $this->assertOwnedBy($customer, $address);

        $this->transaction(function () use ($customer, $address, $data): void {
            $this->addresses->lockForCustomer($customer->id);

            /*
             * Un-ticking "default" on the current default is ignored: a customer
             * always has exactly one default; choose another address to move it.
             */
            $makeDefault = $data->isDefault || $address->is_default;
            $this->addresses->update($address, [...$data->attributes(), 'is_default' => $makeDefault]);

            if ($makeDefault) {
                $this->addresses->clearDefault($customer->id, $address->id);
            }
        });

        return $this->findOrFail($customer, $address->id);
    }

    public function setDefault(Customer $customer, CustomerAddress $address): CustomerAddress
    {
        $this->assertOwnedBy($customer, $address);

        $this->transaction(function () use ($customer, $address): void {
            $this->addresses->lockForCustomer($customer->id);
            $this->addresses->update($address, ['is_default' => true]);
            $this->addresses->clearDefault($customer->id, $address->id);
        });

        return $this->findOrFail($customer, $address->id);
    }

    /**
     * Delete an address; when it was the default the oldest remaining one becomes default.
     */
    public function delete(Customer $customer, CustomerAddress $address): void
    {
        $this->assertOwnedBy($customer, $address);

        $this->transaction(function () use ($customer, $address): void {
            $this->addresses->lockForCustomer($customer->id);
            $wasDefault = $address->is_default;
            $this->addresses->delete($address);

            if ($wasDefault) {
                $oldest = $this->addresses->oldestFor($customer->id);

                if ($oldest !== null) {
                    $this->addresses->update($oldest, ['is_default' => true]);
                }
            }
        });
    }

    public function defaultFor(Customer $customer): ?CustomerAddress
    {
        return $this->addresses->defaultFor($customer->id) ?? $this->addresses->oldestFor($customer->id);
    }

    /**
     * Address step of checkout: shipping = requested (if it is the customer's) → default →
     * first; billing = shipping when "Billing address is same as shipping" (default), else
     * the requested billing address (falls back to shipping when it is not the customer's).
     */
    public function forCheckout(
        Customer $customer,
        ?int $shippingAddressId = null,
        ?int $billingAddressId = null,
        bool $billingSameAsShipping = true,
    ): CheckoutAddressSelectionData {
        $addresses = $this->list($customer);

        $shipping = ($shippingAddressId !== null ? $addresses->firstWhere('id', $shippingAddressId) : null)
            ?? $addresses->firstWhere('is_default', true)
            ?? $addresses->first();

        $billing = $billingSameAsShipping
            ? $shipping
            : (($billingAddressId !== null ? $addresses->firstWhere('id', $billingAddressId) : null) ?? $shipping);

        return new CheckoutAddressSelectionData($customer, $addresses, $shipping, $billing, $billingSameAsShipping || $billing === $shipping);
    }

    public function snapshot(Customer $customer, CustomerAddress $address): OrderAddressSnapshotData
    {
        $this->assertOwnedBy($customer, $address);

        return OrderAddressSnapshotData::fromAddress($address, $customer);
    }

    public function deleteAllFor(Customer $customer): void
    {
        $this->addresses->deleteForCustomer($customer->id);
    }

    private function assertOwnedBy(Customer $customer, CustomerAddress $address): void
    {
        if ($address->customer_id !== $customer->id) {
            throw (new ModelNotFoundException)->setModel(CustomerAddress::class, [$address->id]);
        }
    }
}
