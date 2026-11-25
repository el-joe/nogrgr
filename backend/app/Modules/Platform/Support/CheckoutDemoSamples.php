<?php

namespace App\Modules\Platform\Support;

use App\Shared\Support\Phone;

/**
 * Sample data for the checkout address-step gallery (/admin/ui-kit/storefront-checkout).
 * The address book lives in the admin's session so QA can add, edit and delete
 * addresses against the real component and script without a tenant. Real checkouts
 * read the customer's addresses (Identity) and the store countries (Localization).
 */
final class CheckoutDemoSamples
{
    public const SESSION_KEY = 'ui-kit.checkout.addresses';

    public const SHIPPING_STATES = ['ok', 'no_country', 'unavailable', 'not_sold'];

    /**
     * @return list<array{id: int, iso2: string, name: string, flag: string}>
     */
    public static function countries(): array
    {
        $codes = [1 => 'SA', 2 => 'AE', 3 => 'EG', 4 => 'KW', 5 => 'QA', 6 => 'US'];

        return array_values(array_map(fn (int $id, string $iso2): array => [
            'id' => $id,
            'iso2' => $iso2,
            'name' => __('platform::checkout-demo.countries.'.$iso2),
            'flag' => self::flag($iso2),
        ], array_keys($codes), $codes));
    }

    /**
     * @return array<int, list<string>>
     */
    public static function cities(): array
    {
        return [
            1 => ['Riyadh', 'Jeddah', 'Dammam', 'Mecca', 'Medina', 'Khobar', 'Taif', 'Abha'],
            2 => ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Al Ain'],
            3 => ['Cairo', 'Alexandria', 'Giza', 'Mansoura', 'Luxor'],
            4 => ['Kuwait City', 'Hawalli', 'Salmiya', 'Jahra'],
            5 => ['Doha', 'Al Rayyan', 'Al Wakrah'],
            6 => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Seattle'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function addresses(): array
    {
        return [
            self::present(1, [
                'label' => __('platform::checkout-demo.labels.home'), 'full_name' => 'Sara Alqahtani', 'email' => 'sara@example.com',
                'phone' => '+966501234567', 'phone_country' => 'SA', 'address_line_1' => 'King Fahd Road 2456, Al Olaya',
                'city' => 'Riyadh', 'state' => 'Riyadh Province', 'country_id' => 1, 'latitude' => 24.7136, 'longitude' => 46.6753, 'is_default' => true,
            ]),
            self::present(2, [
                'label' => __('platform::checkout-demo.labels.work'), 'full_name' => 'Sara Alqahtani', 'email' => null,
                'phone' => '+971501234567', 'phone_country' => 'AE', 'address_line_1' => 'Sheikh Zayed Road, Tower 3, Floor 12',
                'city' => 'Dubai', 'state' => null, 'country_id' => 2, 'latitude' => null, 'longitude' => null, 'is_default' => false,
            ]),
        ];
    }

    /**
     * The CustomerAddressResource shape the component and script expect.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function present(int $id, array $fields): array
    {
        $country = collect(self::countries())->firstWhere('id', (int) $fields['country_id']);
        $phone = (string) $fields['phone'];

        return [
            'id' => $id,
            'label' => $fields['label'] ?? null,
            'full_name' => (string) $fields['full_name'],
            'email' => $fields['email'] ?? null,
            'phone' => Phone::e164($phone, (string) $fields['phone_country']) ?? $phone,
            'phone_country' => strtoupper((string) $fields['phone_country']),
            'address_line_1' => (string) $fields['address_line_1'],
            'city' => (string) $fields['city'],
            'state' => $fields['state'] ?? null,
            'country_id' => (int) $fields['country_id'],
            'country' => $country,
            'latitude' => isset($fields['latitude']) ? (float) $fields['latitude'] : null,
            'longitude' => isset($fields['longitude']) ? (float) $fields['longitude'] : null,
            'is_default' => (bool) ($fields['is_default'] ?? false),
            'one_line' => implode(', ', array_filter([$fields['address_line_1'], $fields['city'], $fields['state'] ?? null, $country['name'] ?? null])),
        ];
    }

    private static function flag(string $iso2): string
    {
        return implode('', array_map(fn (string $letter): string => mb_chr(0x1F1E6 + ord($letter) - ord('A')), str_split($iso2)));
    }
}
