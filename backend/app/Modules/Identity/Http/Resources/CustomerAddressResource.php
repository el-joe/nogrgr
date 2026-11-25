<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Address card / form payload (storefront account, checkout, mobile API).
 *
 * @mixin CustomerAddress
 */
class CustomerAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $country = $this->relationLoaded('country') ? $this->country : null;

        return [
            'id' => $this->id,
            'label' => $this->label,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_country' => $this->phone_country,
            'address_line_1' => $this->address_line_1,
            'city' => $this->city,
            'state' => $this->state,
            'country_id' => $this->country_id,
            'country' => $country !== null ? [
                'id' => $country->id,
                'iso2' => $country->iso2,
                'name' => $country->name,
                'flag' => $country->flag,
            ] : null,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'is_default' => $this->is_default,
            'one_line' => implode(', ', array_filter([$this->address_line_1, $this->city, $this->state, $country?->name])),
        ];
    }
}
