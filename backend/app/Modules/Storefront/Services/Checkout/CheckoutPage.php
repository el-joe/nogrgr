<?php

namespace App\Modules\Storefront\Services\Checkout;

use App\Modules\Commerce\DTOs\Checkout\CheckoutSelectionData;
use App\Modules\Commerce\Http\Resources\StoreApi\CheckoutOptionsResource;
use App\Modules\Commerce\Services\Checkout\CheckoutService;
use App\Modules\Identity\Http\Resources\CustomerAddressResource;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Services\CustomerAddressService;
use App\Modules\Localization\Http\Resources\CountryResource;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Shipping\Enums\ShippingUnavailableReason;
use App\Modules\Storefront\Services\StorefrontContext;
use Illuminate\Http\Request;

/**
 * Checkout page data (SF-ECO-023): the Commerce checkout options (addresses, cart summary
 * priced for the shipping address country, payment methods, block reason) through the
 * same CheckoutService the Store API uses, plus what the canonical address modal needs
 * and the shipping info box state of spec 06 §2.
 */
class CheckoutPage
{
    public function __construct(
        private readonly StorefrontCart $cart,
        private readonly CheckoutService $checkout,
        private readonly CustomerAddressService $addresses,
        private readonly StoreCountries $countries,
        private readonly StorefrontContext $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(Request $request, Customer $customer, CheckoutSelectionData $selection): array
    {
        // Applies the context country to a cart that has none (and stores the token).
        $this->cart->data($request);
        $options = $this->checkout->options($this->cart->context($request), $selection);
        $payload = (new CheckoutOptionsResource($options))->toArray($request);

        return [
            'options' => $payload,
            'cart' => $payload['cart'],
            'gateways' => $payload['payment_methods'],
            'addresses' => CustomerAddressResource::collection($this->addresses->list($customer))->resolve($request),
            'countries' => CountryResource::collection($this->countries->available())->resolve($request),
            'phoneCountry' => $this->context->phoneCountry(),
            'shipping' => $this->shippingState($payload['cart']),
            'block' => $payload['block'],
        ];
    }

    /**
     * Shipping info box (spec 06 §2): ok + "zone · carrier · days · price", or one of
     * no_country / unavailable / not_sold (with the product names).
     *
     * @param  array<string, mixed>  $cart
     * @return array{state: string, label: string|null, products: list<string>}
     */
    public function shippingState(array $cart): array
    {
        $notSold = [];

        foreach ($cart['lines'] as $line) {
            if ($line['issue'] === 'not_sold_in_country') {
                $notSold[] = (string) $line['name'];
            }
        }

        if ($notSold !== []) {
            return ['state' => 'not_sold', 'label' => null, 'products' => $notSold];
        }

        $shipping = $cart['shipping'];

        if (! $shipping['available']) {
            $state = match ($shipping['reason']) {
                ShippingUnavailableReason::CountryRequired->value => 'no_country',
                ShippingUnavailableReason::NoBand->value => 'no_band',
                default => 'unavailable',
            };

            return ['state' => $cart['lines'] === [] ? 'ok' : $state, 'label' => null, 'products' => []];
        }

        $delivery = $cart['delivery'];
        $days = $delivery !== null ? __st('checkout.delivery_days', ['min' => $delivery['min_days'], 'max' => $delivery['max_days']]) : null;
        $label = implode(' · ', array_filter([
            $shipping['zone'] ?? null,
            $delivery['company_name'] ?? ($shipping['rate'] ?? null),
            $days,
            $shipping['free'] ? __st('checkout.free') : StorefrontCart::price($shipping['amount']),
        ]));

        return ['state' => 'ok', 'label' => $label !== '' ? $label : null, 'products' => []];
    }
}
