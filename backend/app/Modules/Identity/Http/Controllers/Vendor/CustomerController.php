<?php

namespace App\Modules\Identity\Http\Controllers\Vendor;

use App\Modules\Identity\DataTables\VendorCustomerOrdersDataTable;
use App\Modules\Identity\DataTables\VendorCustomersDataTable;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Enums\CustomerSegment;
use App\Modules\Identity\Http\Requests\Vendor\CustomerCitiesRequest;
use App\Modules\Identity\Http\Requests\Vendor\CustomerRequest;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Services\CustomerAddressService;
use App\Modules\Identity\Services\VendorCustomerService;
use App\Modules\Localization\Models\City;
use App\Modules\Localization\Services\StoreCountries;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use App\Shared\Support\Locale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor › Customers (VEN-025 / 025a / 025b, Figma 12:15544 / 84:107236 / 84:105854):
 * KPI cards + customer list (filters, CSV report, delete), create / edit in a modal,
 * detail with order figures, orders & payments, profile, addresses and activate /
 * deactivate. Runs in the store's tenant context (customers live in the tenant DB).
 */
class CustomerController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly VendorCustomerService $customers,
        private readonly CustomerAddressService $addresses,
        private readonly StoreCountries $countries,
    ) {}

    public function index(): View
    {
        return view('identity::vendor.customers.index', [
            'stats' => $this->customers->stats(),
            'statuses' => AccountStatus::options(),
            'segments' => CustomerSegment::options(),
        ]);
    }

    public function data(Request $request, VendorCustomersDataTable $table): JsonResponse
    {
        return $table->withManage($this->can($request, 'customers.manage'))->toJsonFromRequest($request, VendorCustomersDataTable::FILTERS);
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->customers->export($request->only(VendorCustomersDataTable::FILTERS));
    }

    public function create(Request $request): View
    {
        return $this->form($request, new Customer, true);
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->create($request->toData());

        return JsonResponder::created(__('identity::customers.created', ['name' => $customer->name]), redirect: vendor_route('sales.customers.show', ['customer' => $customer->id]));
    }

    public function show(Request $request, int $customer): View
    {
        $row = $this->customers->find($customer);

        return view('identity::vendor.customers.show', [
            'customer' => $row,
            'summary' => $this->customers->summary($row),
            'subscribed' => $this->customers->isSubscribed($row),
            'addresses' => $this->addresses->list($row),
            'canManage' => $this->can($request, 'customers.manage'),
        ]);
    }

    public function orders(Request $request, int $customer, VendorCustomerOrdersDataTable $table): JsonResponse
    {
        $row = $this->customers->find($customer);

        return $table->forCustomer($row->id, $this->can($request, 'orders.view'))->toJsonFromRequest($request);
    }

    public function edit(Request $request, int $customer): View
    {
        $row = $this->customers->find($customer);

        return $this->form($request, $row, $this->customers->isSubscribed($row) === true);
    }

    public function update(CustomerRequest $request, int $customer): JsonResponse
    {
        $row = $this->customers->update($this->customers->find($customer), $request->toData());

        return JsonResponder::success(__('identity::customers.updated', ['name' => $row->name]), redirect: vendor_route('sales.customers.show', ['customer' => $row->id]));
    }

    public function toggleActive(Request $request, int $customer): JsonResponse
    {
        $row = $this->customers->toggleActive($this->customers->find($customer));
        $message = $row->status === AccountStatus::Active ? 'identity::customers.activated' : 'identity::customers.deactivated';

        return JsonResponder::success(
            __($message, ['name' => $row->name]),
            redirect: $request->boolean('from_detail') ? vendor_route('sales.customers.show', ['customer' => $row->id]) : null,
        );
    }

    public function destroy(Request $request, int $customer): JsonResponse
    {
        $row = $this->customers->find($customer);
        $name = $row->name;
        $this->customers->delete($row);

        return JsonResponder::success(
            __('identity::customers.deleted', ['name' => $name]),
            redirect: $request->boolean('from_detail') ? vendor_route('sales.customers.index') : null,
        );
    }

    /**
     * Select2 options of the address modal's city field: central cities of a store
     * country; the value is the city name (addresses store the city as text).
     */
    public function cities(CustomerCitiesRequest $request): JsonResponse
    {
        $countryId = (int) $request->validated('country_id');
        abort_unless($this->countries->isAvailable($countryId), 404);

        $results = $this->countries->citySuggestions($countryId, $request->validated('q'), 50)
            ->map(static fn (City $city): array => ['id' => (string) $city->name, 'text' => (string) $city->name])
            ->values()
            ->all();

        return response()->json(['results' => $results, 'pagination' => ['more' => false]]);
    }

    private function form(Request $request, Customer $customer, bool $subscribed): View
    {
        $title = $customer->exists ? __('identity::customers.edit_title', ['name' => $customer->name]) : __('identity::customers.create_title');
        $index = vendor_route('sales.customers.index');
        [$first, $last] = array_pad(explode(' ', (string) $customer->name, 2), 2, null);

        return $this->remoteModal($request, 'identity::vendor.customers._form', [
            'customer' => $customer,
            'title' => $title,
            'firstName' => $first,
            'lastName' => $last,
            'subscribed' => $subscribed,
            'locales' => collect(Locale::enabled())->map(static fn (array $locale): string => $locale['native'])->all(),
        ], new RemoteModalPage(
            surface: 'vendor',
            title: $title,
            backUrl: $customer->exists ? vendor_route('sales.customers.show', ['customer' => $customer->id]) : $index,
            pageModule: 'vendor-customers',
            formId: 'vendor-customer-form',
            submitLabel: __('identity::customers.save'),
            breadcrumbs: [__('identity::customers.title') => $index, $title => null],
        ));
    }

    private function can(Request $request, string $permission): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can($permission);
    }
}
