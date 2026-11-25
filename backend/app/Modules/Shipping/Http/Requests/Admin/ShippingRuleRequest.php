<?php

namespace App\Modules\Shipping\Http\Requests\Admin;

use App\Modules\Localization\Models\City;
use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Enums\ShippingRuleStatus;
use App\Modules\Shipping\Support\BandDefinition;
use App\Modules\Shipping\Support\ZoneDefinition;
use App\Shared\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Rule create / edit (ADM-032a): name, status, countries, zones {name, cities, bands
 * {name, min g, max g, price USD}}. Bands of one zone must not overlap, max ≥ min, and a
 * city belongs to one zone of the rule only.
 */
class ShippingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $zones = $this->input('zones');

        if (! is_array($zones)) {
            return;
        }

        foreach ($zones as $z => $zone) {
            foreach ((array) ($zone['bands'] ?? []) as $b => $band) {
                foreach (['min_weight_grams', 'max_weight_grams', 'price'] as $key) {
                    if (is_string($band[$key] ?? null)) {
                        $zones[$z]['bands'][$b][$key] = str_replace([',', '$', ' '], '', $band[$key]);
                    }
                }
            }
        }

        $this->merge(['zones' => array_values($zones)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $countryIds = array_map('intval', array_filter((array) $this->input('country_ids', []), 'is_numeric'));

        return [
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::enum(ShippingRuleStatus::class)],
            'country_ids' => ['required', 'array', 'min:1', 'max:250'],
            'country_ids.*' => ['integer', 'distinct', Rule::exists(Country::class, 'id')],
            'zones' => ['required', 'array', 'min:1', 'max:50'],
            'zones.*.id' => ['nullable', 'integer'],
            'zones.*.name' => ['required', 'string', 'max:120'],
            'zones.*.city_ids' => ['nullable', 'array', 'max:500'],
            'zones.*.city_ids.*' => ['integer', Rule::exists(City::class, 'id')->whereIn('country_id', $countryIds === [] ? [0] : $countryIds)],
            'zones.*.bands' => ['nullable', 'array', 'max:50'],
            'zones.*.bands.*.name' => ['required', 'string', 'max:120'],
            'zones.*.bands.*.min_weight_grams' => ['required', 'integer', 'min:0', 'max:10000000'],
            'zones.*.bands.*.max_weight_grams' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'zones.*.bands.*.price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $seenCities = [];

            foreach ($this->zones() as $z => $zone) {
                foreach ($zone['bands'] as $b => $band) {
                    if ($band['max_weight_grams'] !== null && $band['max_weight_grams'] < $band['min_weight_grams']) {
                        $validator->errors()->add("zones.{$z}.bands.{$b}.max_weight_grams", __('shipping::rules.max_below_min'));
                    }
                }

                $definition = new ZoneDefinition($zone['name'], [], array_map(
                    static fn (array $band): BandDefinition => new BandDefinition($band['name'], $band['min_weight_grams'], $band['max_weight_grams'], Money::ofMinor($band['price'])),
                    $zone['bands'],
                ));

                foreach ($definition->overlappingBands() as [$first, $second]) {
                    $a = $definition->bands[$first];
                    $b = $definition->bands[$second];
                    $validator->errors()->add("zones.{$z}.bands.{$second}.min_weight_grams", __('shipping::rules.band_overlap', [
                        'band' => $b->name,
                        'min' => number_format($b->minGrams),
                        'other' => $a->name,
                        'range' => $a->rangeLabel(),
                    ]));
                }

                foreach ($zone['city_ids'] as $cityId) {
                    if (isset($seenCities[$cityId])) {
                        $validator->errors()->add("zones.{$z}.city_ids", __('shipping::rules.city_in_two_zones', ['zone' => $seenCities[$cityId]]));

                        break;
                    }

                    $seenCities[$cityId] = $zone['name'];
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('shipping::rules.fields.name'),
            'country_ids' => __('shipping::rules.fields.countries'),
            'zones' => __('shipping::rules.zones'),
            'zones.*.name' => __('shipping::rules.fields.zone_name'),
            'zones.*.city_ids' => __('shipping::rules.fields.cities'),
            'zones.*.bands.*.name' => __('shipping::rules.fields.band_name'),
            'zones.*.bands.*.min_weight_grams' => __('shipping::rules.fields.min_weight'),
            'zones.*.bands.*.max_weight_grams' => __('shipping::rules.fields.max_weight'),
            'zones.*.bands.*.price' => __('shipping::rules.fields.price'),
        ];
    }

    /**
     * @return list<array{id: int|null, name: string, city_ids: list<int>, bands: list<array{name: string, min_weight_grams: int, max_weight_grams: int|null, price: int}>}>
     */
    public function zones(): array
    {
        $zones = [];

        foreach (array_values((array) $this->input('zones', [])) as $zone) {
            $zones[] = [
                'id' => isset($zone['id']) && is_numeric($zone['id']) ? (int) $zone['id'] : null,
                'name' => trim((string) ($zone['name'] ?? '')),
                'city_ids' => array_values(array_unique(array_map('intval', (array) ($zone['city_ids'] ?? [])))),
                'bands' => array_values(array_map(static fn (array $band): array => [
                    'name' => trim((string) ($band['name'] ?? '')),
                    'min_weight_grams' => (int) ($band['min_weight_grams'] ?? 0),
                    'max_weight_grams' => isset($band['max_weight_grams']) && $band['max_weight_grams'] !== '' ? (int) $band['max_weight_grams'] : null,
                    'price' => Money::of(is_numeric($band['price'] ?? null) ? (string) $band['price'] : '0')->minor,
                ], array_filter((array) ($zone['bands'] ?? []), 'is_array'))),
            ];
        }

        return $zones;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'name' => trim((string) $this->validated('name')),
            'status' => ShippingRuleStatus::from((string) $this->validated('status')),
            'country_ids' => array_values(array_map('intval', (array) $this->validated('country_ids'))),
            'zones' => $this->zones(),
        ];
    }
}
