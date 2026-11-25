<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Platform\Http\Requests\Admin\CheckoutDemoAddressRequest;
use App\Modules\Platform\Http\Requests\Admin\CheckoutDemoRequest;
use App\Modules\Platform\Support\CheckoutDemoSamples;
use App\Modules\Platform\Support\MotionDemoSamples;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Checkout address-step gallery (/admin/ui-kit/storefront-checkout): the real
 * x-sf::checkout.address-step and its script, rendered through the chosen family's
 * storefront bundle, backed by a session address book and sample geo endpoints so
 * QA can walk every state (empty, add / edit, billing list, shipping errors, no
 * gateway) without a tenant (permission system.ui-kit.view).
 */
class CheckoutDemoController extends Controller
{
    public function show(CheckoutDemoRequest $request): View
    {
        if ($request->direction() !== null) {
            app()->setLocale($request->direction() === 'rtl' ? 'ar' : 'en');
        }

        $addresses = $request->startsEmpty() ? [] : CheckoutDemoSamples::addresses();
        $request->session()->put(CheckoutDemoSamples::SESSION_KEY, $addresses);
        $state = $request->shippingState();

        return view('platform::admin.storefront-checkout', [
            'family' => $request->family(),
            'families' => MotionDemoSamples::FAMILIES,
            'states' => CheckoutDemoSamples::SHIPPING_STATES,
            'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            'addresses' => $addresses,
            'countries' => CheckoutDemoSamples::countries(),
            'empty' => $request->startsEmpty(),
            'gatewayAvailable' => $request->gatewayAvailable(),
            'shipping' => [
                'state' => $state,
                'label' => $state === 'ok' ? __('platform::checkout-demo.shipping_label') : null,
                'products' => $state === 'not_sold' ? [__('platform::checkout-demo.product')] : [],
            ],
            'urls' => [
                'store' => route('admin.ui-kit.checkout.store'),
                'update' => route('admin.ui-kit.checkout.update', ['address' => '__ID__']),
                'destroy' => route('admin.ui-kit.checkout.destroy', ['address' => '__ID__']),
                'default' => route('admin.ui-kit.checkout.default', ['address' => '__ID__']),
                'cities' => route('admin.ui-kit.checkout.cities', ['country' => '__COUNTRY__']),
                'reverse' => route('admin.ui-kit.checkout.reverse'),
                'ip' => route('admin.ui-kit.checkout.ip'),
            ],
        ]);
    }

    public function store(CheckoutDemoAddressRequest $request): JsonResponse
    {
        $addresses = $this->addresses($request);
        $id = (int) (collect($addresses)->max('id') ?? 0) + 1;
        $address = CheckoutDemoSamples::present($id, ['is_default' => $addresses === [] || $request->boolean('is_default')] + $request->validated());
        $addresses[] = $address;

        return $this->saved($request, $addresses, $address, __('identity::addresses.saved'));
    }

    public function update(CheckoutDemoAddressRequest $request, int $address): JsonResponse
    {
        $addresses = $this->addresses($request);
        $index = $this->indexOf($addresses, $address);
        $saved = CheckoutDemoSamples::present($address, ['is_default' => $addresses[$index]['is_default'] || $request->boolean('is_default')] + $request->validated());
        $addresses[$index] = $saved;

        return $this->saved($request, $addresses, $saved, __('identity::addresses.updated'));
    }

    public function setDefault(Request $request, int $address): JsonResponse
    {
        $addresses = $this->addresses($request);
        $index = $this->indexOf($addresses, $address);

        return $this->saved($request, $this->makeDefault($addresses, $address), $addresses[$index], __('identity::addresses.default_set'));
    }

    public function destroy(Request $request, int $address): JsonResponse
    {
        $addresses = $this->addresses($request);
        $index = $this->indexOf($addresses, $address);
        $wasDefault = $addresses[$index]['is_default'];
        array_splice($addresses, $index, 1);

        if ($wasDefault && $addresses !== []) {
            $addresses = $this->makeDefault($addresses, $addresses[0]['id']);
        }

        $request->session()->put(CheckoutDemoSamples::SESSION_KEY, $addresses);

        return JsonResponder::success(__('identity::addresses.deleted'), ['addresses' => $this->sorted($addresses)]);
    }

    public function cities(Request $request, int $country): JsonResponse
    {
        $query = Str::lower(trim((string) $request->query('q', '')));
        $names = CheckoutDemoSamples::cities()[$country] ?? abort(404);
        $matches = array_values(array_filter($names, fn (string $name): bool => $query === '' || str_contains(Str::lower($name), $query)));

        return JsonResponder::success(data: array_map(fn (string $name, int $index): array => ['id' => $country * 100 + $index, 'name' => $name, 'type' => 'city'], $matches, array_keys($matches)));
    }

    public function reverse(Request $request): JsonResponse
    {
        $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lng' => ['required', 'numeric', 'between:-180,180']]);

        return JsonResponder::success(data: [
            'address_line_1' => 'Prince Mohammed Bin Abdulaziz Rd 7421, Al Olaya', 'city' => 'Riyadh', 'state' => 'Riyadh Province',
            'country_id' => 1, 'country_iso2' => 'SA', 'latitude' => (float) $request->input('lat'), 'longitude' => (float) $request->input('lng'), 'approximate' => false,
        ]);
    }

    public function ip(): JsonResponse
    {
        return JsonResponder::success(__('localization::geo.approximate'), [
            'address_line_1' => null, 'city' => 'Jeddah', 'state' => 'Makkah Province', 'country_id' => 1, 'country_iso2' => 'SA',
            'latitude' => 21.5433, 'longitude' => 39.1728, 'approximate' => true,
        ]);
    }

    /**
     * Echoes what the checkout would receive from the step's hidden inputs.
     */
    public function pay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_address_id' => ['required', 'integer'],
            'billing_address_id' => ['required', 'integer'],
            'billing_same_as_shipping' => ['required', 'boolean'],
        ]);

        return JsonResponder::success(__('platform::checkout-demo.paid', [
            'shipping' => $validated['shipping_address_id'],
            'billing' => $validated['billing_address_id'],
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function addresses(Request $request): array
    {
        $addresses = $request->session()->get(CheckoutDemoSamples::SESSION_KEY, []);

        return is_array($addresses) ? array_values($addresses) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $addresses
     */
    private function indexOf(array $addresses, int $id): int
    {
        $index = array_search($id, array_column($addresses, 'id'), true);

        abort_if($index === false, 404);

        return (int) $index;
    }

    /**
     * @param  list<array<string, mixed>>  $addresses
     * @return list<array<string, mixed>>
     */
    private function makeDefault(array $addresses, int $id): array
    {
        return array_map(fn (array $address): array => ['is_default' => $address['id'] === $id] + $address, $addresses);
    }

    /**
     * @param  list<array<string, mixed>>  $addresses
     * @return list<array<string, mixed>>
     */
    private function sorted(array $addresses): array
    {
        usort($addresses, fn (array $a, array $b): int => [$b['is_default'], $a['id']] <=> [$a['is_default'], $b['id']]);

        return $addresses;
    }

    /**
     * @param  list<array<string, mixed>>  $addresses
     * @param  array<string, mixed>  $address
     */
    private function saved(Request $request, array $addresses, array $address, string $message): JsonResponse
    {
        if ($request->boolean('is_default')) {
            $addresses = $this->makeDefault($addresses, (int) $address['id']);
        }

        $request->session()->put(CheckoutDemoSamples::SESSION_KEY, $addresses);
        $address = collect($addresses)->firstWhere('id', $address['id']) ?? $address;

        return JsonResponder::success($message, ['address' => $address, 'addresses' => $this->sorted($addresses)]);
    }
}
