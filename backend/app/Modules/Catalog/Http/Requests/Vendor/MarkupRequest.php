<?php

namespace App\Modules\Catalog\Http\Requests\Vendor;

use App\Modules\Catalog\DTOs\Vendor\MarkupData;
use App\Modules\Commerce\Enums\ProfitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Price list modal (VEN-010a): markup mode + value, optional country / variant scope;
 * applying also names the store products (one product from the modal, many from the list).
 */
class MarkupRequest extends FormRequest
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
        $applying = str_ends_with((string) $this->route()?->getName(), 'price-list.store');

        return [
            'profit_type' => ['required', Rule::enum(ProfitType::class)],
            'profit_value' => ['required', 'numeric', 'min:0', $this->input('profit_type') === ProfitType::Percentage->value ? 'max:1000' : 'max:1000000'],
            'country_id' => ['nullable', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'product_ids' => [$applying ? 'required' : 'nullable', 'array', 'min:1', 'max:1000'],
            'product_ids.*' => ['integer', 'min:1'],
        ];
    }

    public function toData(): MarkupData
    {
        return new MarkupData(
            ProfitType::from((string) $this->validated('profit_type')),
            round((float) $this->validated('profit_value'), 2),
            $this->filled('country_id') ? (int) $this->validated('country_id') : null,
            $this->filled('variant_id') ? (int) $this->validated('variant_id') : null,
        );
    }

    /**
     * @return list<int>
     */
    public function productIds(): array
    {
        return array_values(array_map('intval', (array) $this->validated('product_ids', [])));
    }
}
