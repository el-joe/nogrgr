<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\DTOs\Imports\CategoryMappingData;
use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Save the source category mapping: mappings[<map id>] = central category id | empty.
 */
class ImportCategoryMapRequest extends FormRequest
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
            'driver' => ['required', 'string', Rule::enum(ImportDriver::class)],
            'mappings' => ['required', 'array', 'max:2000'],
            'mappings.*' => ['nullable', 'integer', Rule::exists(Category::class, 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_keys((array) $this->input('mappings', [])) as $key) {
                    if (! ctype_digit((string) $key)) {
                        $validator->errors()->add('mappings', __('catalog::imports.mapping.invalid'));

                        return;
                    }
                }
            },
        ];
    }

    public function driver(): ImportDriver
    {
        return ImportDriver::from((string) $this->validated('driver'));
    }

    public function toData(): CategoryMappingData
    {
        $mappings = [];

        foreach ((array) $this->validated('mappings') as $mapId => $categoryId) {
            $mappings[(int) $mapId] = $categoryId !== null && $categoryId !== '' ? (int) $categoryId : null;
        }

        return new CategoryMappingData($mappings);
    }
}
