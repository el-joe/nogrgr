<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\CustomerAddress;
use App\Modules\Identity\Repositories\Contracts\CustomerAddressRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Country used for storefront pricing and shipping (old CustomerCountryResolver order):
 *
 *   1. the explicitly selected address (checkout shipping address), when it is the customer's;
 *   2. an explicitly entered country id;
 *   3. the signed-in customer's default (else oldest) saved address;
 *   4. the visitor's session country (SESSION_KEY, set by the storefront country switch).
 *
 * Product visibility keeps using the detected visitor country; this only drives prices/shipping.
 */
class CustomerCountryResolver
{
    public const SESSION_KEY = 'storefront.country_id';

    /**
     * @var array<int, CustomerAddress|null> per-request memo of default addresses (GET only)
     */
    private array $defaults = [];

    public function __construct(
        private readonly CustomerAddressRepositoryContract $addresses,
        private readonly CountryRepositoryContract $countries,
        private readonly Request $request,
    ) {}

    public function resolveId(?int $selectedAddressId = null, ?int $explicitCountryId = null): ?int
    {
        $customer = $this->customer();

        if ($selectedAddressId !== null && $customer !== null) {
            $address = $this->addresses->findForCustomer($customer->id, $selectedAddressId);

            if ($address !== null) {
                return $address->country_id;
            }
        }

        if ($explicitCountryId !== null) {
            return $explicitCountryId;
        }

        if ($customer !== null) {
            $default = $this->defaultAddress($customer);

            if ($default !== null) {
                return $default->country_id;
            }
        }

        $session = session()->get(self::SESSION_KEY);

        return is_numeric($session) ? (int) $session : null;
    }

    public function resolveIso2(?int $selectedAddressId = null, ?int $explicitCountryId = null): ?string
    {
        $id = $this->resolveId($selectedAddressId, $explicitCountryId);

        return $id !== null ? $this->countries->find($id)?->iso2 : null;
    }

    private function customer(): ?Customer
    {
        $user = Auth::guard('customer')->user();

        return $user instanceof Customer ? $user : null;
    }

    private function defaultAddress(Customer $customer): ?CustomerAddress
    {
        $lookup = fn (): ?CustomerAddress => $this->addresses->defaultFor($customer->id) ?? $this->addresses->oldestFor($customer->id);

        if (! $this->request->isMethodSafe()) {
            return $lookup();
        }

        if (! array_key_exists($customer->id, $this->defaults)) {
            $this->defaults[$customer->id] = $lookup();
        }

        return $this->defaults[$customer->id];
    }
}
