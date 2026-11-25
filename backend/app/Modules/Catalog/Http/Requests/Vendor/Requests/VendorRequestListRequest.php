<?php

namespace App\Modules\Catalog\Http\Requests\Vendor\Requests;

use App\Shared\DataTables\DataTableFilters;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters of the vendor request lists / export: status ("open" or a status value), search.
 */
class VendorRequestListRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = DataTableFilters::fromRequest($this, ['search', 'status']);

        if (isset($filters['search']) && is_string($filters['search'])) {
            $filters['search'] = mb_substr($filters['search'], 0, 100);
        }

        return $filters;
    }
}
