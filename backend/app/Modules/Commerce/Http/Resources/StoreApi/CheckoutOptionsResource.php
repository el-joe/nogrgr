<?php

namespace App\Modules\Commerce\Http\Resources\StoreApi;

use App\Modules\Commerce\DTOs\Checkout\CheckoutOptions;
use App\Modules\Commerce\DTOs\Payments\GatewayOption;
use App\Modules\Identity\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Checkout data: saved addresses + the selected shipping / billing ones, the cart summary
 * for the shipping country, payment methods (alphabetical, none selected) and the reason
 * the pay button is disabled.
 *
 * @property CheckoutOptions $resource
 */
class CheckoutOptionsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $options = $this->resource;
        $addresses = $options->addresses;

        return [
            'addresses' => $addresses->addresses->map(static fn (CustomerAddress $address): array => [
                'id' => $address->id,
                'label' => $address->label,
                'full_name' => $address->full_name,
                'phone' => $address->phone,
                'is_default' => (bool) $address->is_default,
                'country_id' => $address->country_id,
                'one_line' => implode(', ', array_filter([$address->address_line_1, $address->city, $address->state])),
            ])->values()->all(),
            'shipping_address_id' => $addresses->shipping?->id,
            'billing_address_id' => $addresses->billingSameAsShipping ? $addresses->shipping?->id : $addresses->billing?->id,
            'billing_same_as_shipping' => $addresses->billingSameAsShipping,
            'cart' => (new CartResource($options->summary))->toArray($request),
            'payment_methods' => array_map(static fn (GatewayOption $option): array => $option->toArray(), $options->gateways),
            'selected_payment_method' => null,
            'can_pay' => $options->block === null,
            'block' => $options->block?->value,
            'block_message' => $options->block?->message($options->summary->composed->shipping),
        ];
    }
}
