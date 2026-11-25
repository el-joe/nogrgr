<?php

namespace App\Modules\Catalog\Http\Requests\Vendor;

use App\Modules\Catalog\Enums\OwnProductSection;
use App\Modules\Catalog\Enums\StoreProductFilter;
use App\Shared\DataTables\DataTableFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of the vendor catalog lists / exports / option searches (query string; the
 * DataTables protocol keys are read separately by DataTableFilters).
 */
class ListFilterRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(StoreProductFilter::class)],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'section' => ['nullable', Rule::enum(OwnProductSection::class)],
            'added' => ['nullable', Rule::in(['yes', 'no'])],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * Validated filters; `search` is read from DataTables' search[value] or a plain
     * ?search= / Select2 ?q= term.
     *
     * @return array<string, string|list<string>>
     */
    public function filters(): array
    {
        $filters = DataTableFilters::fromRequest($this, ['search', 'status', 'category_id', 'section', 'added']);

        if (! isset($filters['search']) && is_string($this->validated('q')) && trim((string) $this->validated('q')) !== '') {
            $filters['search'] = trim((string) $this->validated('q'));
        }

        if (isset($filters['search']) && is_string($filters['search'])) {
            $filters['search'] = mb_substr($filters['search'], 0, 100);
        }

        return $filters;
    }
}
