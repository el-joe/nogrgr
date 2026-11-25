<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Enums\ProductListTab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Products list page / export: ?tab=… plus the filters of the table.
 */
class ProductListRequest extends FormRequest
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
            'tab' => ['nullable', Rule::enum(ProductListTab::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:20'],
            'category_id' => ['nullable', 'integer'],
            'scope' => ['nullable', 'string', 'max:20'],
            'stock' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function tab(): ProductListTab
    {
        return ProductListTab::tryFrom((string) $this->query('tab')) ?? ProductListTab::All;
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [...$this->safe()->except('tab'), 'tab' => $this->tab()->value];
    }
}
