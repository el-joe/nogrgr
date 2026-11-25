<?php

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\CustomerAddress;
use App\Shared\DTOs\BaseDTO;

/**
 * Address copied onto an order (`orders.shipping_address` / `orders.billing_address`
 * JSON, Phase 5). Self-contained: the country name is captured in every language at
 * order time, so later edits or deletes of the saved address never change the order.
 * `email` falls back to the customer's account email (spec §1).
 */
final class OrderAddressSnapshotData extends BaseDTO
{
    /**
     * @param  array<string, string>  $countryName  locale => name
     */
    public function __construct(
        public readonly ?int $addressId,
        public readonly ?string $label,
        public readonly string $fullName,
        public readonly ?string $email,
        public readonly string $phone,
        public readonly string $phoneCountry,
        public readonly string $addressLine1,
        public readonly string $city,
        public readonly ?string $state,
        public readonly int $countryId,
        public readonly ?string $countryIso2,
        public readonly array $countryName,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
    ) {}

    public static function fromAddress(CustomerAddress $address, ?Customer $customer = null): self
    {
        $country = $address->country;

        /** @var array<string, string> $names */
        $names = $country !== null ? $country->getTranslations('name') : [];

        return new self(
            addressId: $address->id,
            label: $address->label,
            fullName: $address->full_name,
            email: $address->email ?? $customer?->email,
            phone: $address->phone,
            phoneCountry: $address->phone_country,
            addressLine1: $address->address_line_1,
            city: $address->city,
            state: $address->state,
            countryId: $address->country_id,
            countryIso2: $country?->iso2,
            countryName: $names,
            latitude: $address->latitude !== null ? (float) $address->latitude : null,
            longitude: $address->longitude !== null ? (float) $address->longitude : null,
        );
    }

    /**
     * One-line address for order lists, invoices and emails.
     */
    public function oneLine(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $country = $this->countryName[$locale] ?? $this->countryName['en'] ?? $this->countryIso2;

        return implode(', ', array_filter([$this->addressLine1, $this->city, $this->state, $country]));
    }
}
