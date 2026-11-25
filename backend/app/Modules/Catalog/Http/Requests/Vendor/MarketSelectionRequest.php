<?php

namespace App\Modules\Catalog\Http\Requests\Vendor;

use App\Modules\Catalog\DTOs\Vendor\MarketSelectionData;
use App\Modules\Catalog\Enums\MarketPlacement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Select markets modal (VEN-015a): countries[] + placements[<country id>][].
 */
class MarketSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'countries' => ['required', 'array', 'min:1', 'max:250'],
            'countries.*' => ['integer', 'min:1', 'distinct'],
            'placements' => ['nullable', 'array'],
            'placements.*' => ['array'],
            'placements.*.*' => [Rule::enum(MarketPlacement::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['countries.required' => (string) __('catalog::vendor.errors.no_markets')];
    }

    public function toData(): MarketSelectionData
    {
        $placements = (array) $this->validated('placements', []);
        $markets = [];

        foreach ((array) $this->validated('countries') as $countryId) {
            $markets[(int) $countryId] = array_values(array_map('strval', (array) ($placements[$countryId] ?? [])));
        }

        return new MarketSelectionData($markets);
    }
}
