<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\DTOs\ProductBulkData;
use App\Modules\Catalog\Enums\ProductBulkAction;
use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Products list bulk action: {action: activate|deactivate|delete, ids: [..]} (selected rows).
 */
class ProductBulkRequest extends FormRequest
{
    public const MAX_IDS = 500;

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
            'action' => ['required', Rule::enum(ProductBulkAction::class)],
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer', 'distinct', Rule::exists(Product::class, 'id')->whereNull('deleted_at')],
        ];
    }

    public function bulkData(): ProductBulkData
    {
        return new ProductBulkData(
            action: ProductBulkAction::from((string) $this->validated('action')),
            productIds: array_values(array_map('intval', (array) $this->validated('ids'))),
        );
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'action' => __('catalog::products.bulk.action'),
            'ids' => __('catalog::products.bulk.selection'),
            'ids.*' => __('catalog::products.bulk.selection'),
        ];
    }
}
