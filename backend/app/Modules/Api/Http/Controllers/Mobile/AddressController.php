<?php

namespace App\Modules\Api\Http\Controllers\Mobile;

use App\Modules\Api\Http\Concerns\ResolvesMobileCustomer;
use App\Modules\Identity\DTOs\CustomerAddressData;
use App\Modules\Identity\Http\Requests\Storefront\SaveCustomerAddressRequest;
use App\Modules\Identity\Http\Resources\CustomerAddressResource;
use App\Modules\Identity\Services\CustomerAddressService;
use App\Modules\Localization\Http\Resources\CountryResource;
use App\Modules\Localization\Services\StoreCountries;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account › Addresses (APP-028 / APP-028a): list, add, edit, default, delete, plus the
 * store's shipping countries for the form (intl phone: number + ISO2 country). Another
 * customer's address id answers 404.
 */
class AddressController extends Controller
{
    use ResolvesMobileCustomer;

    public function __construct(
        private readonly CustomerAddressService $addresses,
        private readonly StoreCountries $countries,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return JsonResponder::success('', [
            'addresses' => $this->collection($request),
            'countries' => CountryResource::collection($this->countries->available())->resolve($request),
        ]);
    }

    public function store(SaveCustomerAddressRequest $request): JsonResponse
    {
        $address = $this->addresses->create($this->customer($request), CustomerAddressData::fromRequest($request));

        return JsonResponder::created((string) __('identity::addresses.saved'), [
            'address' => (new CustomerAddressResource($address))->resolve($request),
            'addresses' => $this->collection($request),
        ]);
    }

    public function update(SaveCustomerAddressRequest $request, int $address): JsonResponse
    {
        $customer = $this->customer($request);
        $updated = $this->addresses->update($customer, $this->addresses->findOrFail($customer, $address), CustomerAddressData::fromRequest($request));

        return JsonResponder::success((string) __('identity::addresses.updated'), [
            'address' => (new CustomerAddressResource($updated))->resolve($request),
            'addresses' => $this->collection($request),
        ]);
    }

    public function setDefault(Request $request, int $address): JsonResponse
    {
        $customer = $this->customer($request);
        $updated = $this->addresses->setDefault($customer, $this->addresses->findOrFail($customer, $address));

        return JsonResponder::success((string) __('identity::addresses.default_set'), [
            'address' => (new CustomerAddressResource($updated))->resolve($request),
            'addresses' => $this->collection($request),
        ]);
    }

    public function destroy(Request $request, int $address): JsonResponse
    {
        $customer = $this->customer($request);
        $this->addresses->delete($customer, $this->addresses->findOrFail($customer, $address));

        return JsonResponder::success((string) __('identity::addresses.deleted'), ['addresses' => $this->collection($request)]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collection(Request $request): array
    {
        return CustomerAddressResource::collection($this->addresses->list($this->customer($request)))->resolve($request);
    }
}
