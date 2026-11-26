<?php

namespace App\Modules\Reporting\Http\Requests\Admin;

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Enums\ShopperStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shoppers filters (ADM-043): store, country, status, new only, search.
 */
class ShopperFilterRequest extends FormRequest
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
            'tenant_id' => ['nullable', 'integer', Rule::exists('tenants', 'id')],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'status' => ['nullable', Rule::enum(ShopperStatus::class)],
            'new_only' => ['nullable', 'boolean'],
            'search' => ['nullable'],
        ];
    }

    public function filters(): ReportFilterData
    {
        return ReportFilterData::fromInput($this->validated());
    }

    /**
     * @return array{search: string|null, status: string|null, new_only: bool}
     */
    public function options(): array
    {
        $search = $this->input('search');
        $search = is_array($search) ? ($search['value'] ?? null) : $search;

        return [
            'search' => is_string($search) ? mb_substr(trim($search), 0, 100) : null,
            'status' => $this->validated('status'),
            'new_only' => $this->boolean('new_only'),
        ];
    }
}
