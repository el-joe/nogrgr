<?php

namespace App\Modules\Identity\Http\Controllers\Vendor;

use App\Modules\Identity\DTOs\CustomerAddressData;
use App\Modules\Identity\Http\Requests\Storefront\SaveCustomerAddressRequest;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\CustomerAddress;
use App\Modules\Identity\Services\CustomerAddressService;
use App\Modules\Identity\Services\VendorCustomerService;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Services\StoreCountries;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use App\Shared\Support\Locale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Customers › detail › addresses (VEN-025c, Figma 84:107236 "Add address"):
 * create / edit in a modal (country → cities of that country, street, label, default,
 * plus the canonical form's name and phone), make default and delete. Validation is
 * the canonical SaveCustomerAddressRequest; the rules are CustomerAddressService's.
 */
class CustomerAddressController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly VendorCustomerService $customers,
        private readonly CustomerAddressService $addresses,
        private readonly StoreCountries $countries,
    ) {}

    public function create(Request $request, int $customer): View
    {
        $row = $this->customers->find($customer);

        return $this->form($request, $row, new CustomerAddress([
            'full_name' => $row->name,
            'email' => $row->email,
            'phone' => $row->phone,
            'phone_country' => $row->phone_country,
            'is_default' => $this->addresses->list($row)->isEmpty(),
        ]));
    }

    public function store(SaveCustomerAddressRequest $request, int $customer): JsonResponse
    {
        $row = $this->customers->find($customer);
        $this->addresses->create($row, CustomerAddressData::fromRequest($request));

        return JsonResponder::created(__('identity::customers.addresses.saved'), redirect: $this->back($row));
    }

    public function edit(Request $request, int $customer, int $address): View
    {
        $row = $this->customers->find($customer);

        return $this->form($request, $row, $this->addresses->findOrFail($row, $address));
    }

    public function update(SaveCustomerAddressRequest $request, int $customer, int $address): JsonResponse
    {
        $row = $this->customers->find($customer);
        $this->addresses->update($row, $this->addresses->findOrFail($row, $address), CustomerAddressData::fromRequest($request));

        return JsonResponder::success(__('identity::customers.addresses.updated'), redirect: $this->back($row));
    }

    public function makeDefault(int $customer, int $address): JsonResponse
    {
        $row = $this->customers->find($customer);
        $this->addresses->setDefault($row, $this->addresses->findOrFail($row, $address));

        return JsonResponder::success(__('identity::customers.addresses.default_set'), redirect: $this->back($row));
    }

    public function destroy(int $customer, int $address): JsonResponse
    {
        $row = $this->customers->find($customer);
        $this->addresses->delete($row, $this->addresses->findOrFail($row, $address));

        return JsonResponder::success(__('identity::customers.addresses.deleted'), redirect: $this->back($row));
    }

    private function form(Request $request, Customer $customer, CustomerAddress $address): View
    {
        $title = $address->exists ? __('identity::customers.addresses.edit_title') : __('identity::customers.addresses.create_title');
        $available = $this->countries->available();
        $countryId = $address->country_id
            ?? $available->firstWhere('iso2', strtoupper((string) ($customer->country_code ?? $customer->phone_country ?? Locale::visitorCountry())))?->id
            ?? $available->first()?->id;
        $labels = collect(['home', 'work', 'other'])->mapWithKeys(static fn (string $key): array => [(string) __('identity::customers.addresses.labels.'.$key) => (string) __('identity::customers.addresses.labels.'.$key)]);

        if ($address->label !== null && ! $labels->has($address->label)) {
            $labels->put($address->label, $address->label);
        }

        return $this->remoteModal($request, 'identity::vendor.customers._address-form', [
            'customer' => $customer,
            'address' => $address,
            'title' => $title,
            'countries' => $available->mapWithKeys(static fn (Country $country): array => [$country->id => $country->name])->all(),
            'countryId' => $countryId,
            'labels' => $labels->all(),
            'phoneCountry' => $address->phone_country ?? $customer->phone_country,
        ], new RemoteModalPage(
            surface: 'vendor',
            title: $title,
            backUrl: $this->back($customer),
            pageModule: 'vendor-customers',
            formId: 'vendor-address-form',
            submitLabel: __('identity::customers.save'),
            breadcrumbs: [__('identity::customers.title') => vendor_route('sales.customers.index'), $customer->name => $this->back($customer), $title => null],
        ));
    }

    private function back(Customer $customer): string
    {
        return vendor_route('sales.customers.show', ['customer' => $customer->id]);
    }
}
