<?php

namespace App\Modules\Identity\Http\Controllers\Storefront;

use App\Modules\Identity\DTOs\CustomerAddressData;
use App\Modules\Identity\Http\Requests\Storefront\SaveCustomerAddressRequest;
use App\Modules\Identity\Http\Resources\CustomerAddressResource;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Services\CustomerAddressService;
use App\Modules\Localization\Http\Resources\CountryResource;
use App\Modules\Localization\Services\StoreCountries;
use App\Shared\Http\Concerns\RespondsWithJsonOrRedirect;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Support\Locale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Account › Addresses ({store}/account/addresses) and the JSON endpoints the
 * canonical address modal uses on the account page and in checkout. An address id
 * of another customer (or another store) answers 404. Mutations answer JSON for
 * fetch() calls and redirect back with a `status` flash for plain form posts.
 */
class AddressController extends Controller
{
    use RespondsWithJsonOrRedirect;

    public function __construct(
        private readonly CustomerAddressService $addresses,
        private readonly StoreCountries $countries,
    ) {}

    public function index(Request $request): View
    {
        return view('identity::storefront.account.addresses', [
            'addresses' => CustomerAddressResource::collection($this->addresses->list($this->customer($request)))->resolve($request),
            'countries' => CountryResource::collection($this->countries->available())->resolve($request),
            'phoneCountry' => strtoupper(Locale::visitorCountry()),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        return JsonResponder::success(data: $this->collection($request));
    }

    public function store(SaveCustomerAddressRequest $request): JsonResponse|RedirectResponse
    {
        $customer = $this->customer($request);
        $address = $this->addresses->create($customer, CustomerAddressData::fromRequest($request));

        return $this->respond($request, __('identity::addresses.saved'), [
            'address' => (new CustomerAddressResource($address))->resolve($request),
            'addresses' => $this->collection($request),
        ], status: 201);
    }

    public function update(SaveCustomerAddressRequest $request, int $address): JsonResponse|RedirectResponse
    {
        $customer = $this->customer($request);
        $updated = $this->addresses->update($customer, $this->addresses->findOrFail($customer, $address), CustomerAddressData::fromRequest($request));

        return $this->respond($request, __('identity::addresses.updated'), [
            'address' => (new CustomerAddressResource($updated))->resolve($request),
            'addresses' => $this->collection($request),
        ]);
    }

    public function setDefault(Request $request, int $address): JsonResponse|RedirectResponse
    {
        $customer = $this->customer($request);
        $updated = $this->addresses->setDefault($customer, $this->addresses->findOrFail($customer, $address));

        return $this->respond($request, __('identity::addresses.default_set'), [
            'address' => (new CustomerAddressResource($updated))->resolve($request),
            'addresses' => $this->collection($request),
        ]);
    }

    public function destroy(Request $request, int $address): JsonResponse|RedirectResponse
    {
        $customer = $this->customer($request);
        $this->addresses->delete($customer, $this->addresses->findOrFail($customer, $address));

        return $this->respond($request, __('identity::addresses.deleted'), ['addresses' => $this->collection($request)]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collection(Request $request): array
    {
        return CustomerAddressResource::collection($this->addresses->list($this->customer($request)))->resolve($request);
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user('customer');
        abort_unless($customer instanceof Customer, 401);

        return $customer;
    }
}
