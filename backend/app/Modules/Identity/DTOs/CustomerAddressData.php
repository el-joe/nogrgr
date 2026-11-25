<?php

namespace App\Modules\Identity\DTOs;

use App\Shared\DTOs\BaseDTO;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Canonical address form (spec §1) after SaveCustomerAddressRequest: `phone` is E.164,
 * `phoneCountry` / ISO2 upper-case, empty optional strings are null.
 */
final class CustomerAddressData extends BaseDTO
{
    public function __construct(
        public readonly string $fullName,
        public readonly string $phone,
        public readonly string $phoneCountry,
        public readonly string $addressLine1,
        public readonly string $city,
        public readonly int $countryId,
        public readonly ?string $label = null,
        public readonly ?string $email = null,
        public readonly ?string $state = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly bool $isDefault = false,
    ) {}

    public static function fromRequest(FormRequest $request): static
    {
        $country = strtoupper((string) $request->validated('phone_country'));
        $optional = static function (mixed $value): ?string {
            $value = is_string($value) ? trim($value) : null;

            return $value === '' ? null : $value;
        };
        $latitude = $request->validated('latitude');
        $longitude = $request->validated('longitude');
        $email = $optional($request->validated('email'));

        return new self(
            fullName: trim((string) $request->validated('full_name')),
            phone: (string) $request->validated('phone'),
            phoneCountry: $country,
            addressLine1: trim((string) $request->validated('address_line_1')),
            city: trim((string) $request->validated('city')),
            countryId: (int) $request->validated('country_id'),
            label: $optional($request->validated('label')),
            email: $email !== null ? mb_strtolower($email) : null,
            state: $optional($request->validated('state')),
            latitude: is_numeric($latitude) ? (float) $latitude : null,
            longitude: is_numeric($longitude) ? (float) $longitude : null,
            isDefault: $request->boolean('is_default'),
        );
    }

    /**
     * Columns of `customer_addresses` (without customer_id / is_default).
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'label' => $this->label,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_country' => $this->phoneCountry,
            'address_line_1' => $this->addressLine1,
            'city' => $this->city,
            'state' => $this->state,
            'country_id' => $this->countryId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
