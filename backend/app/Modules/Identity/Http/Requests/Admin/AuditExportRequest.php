<?php

namespace App\Modules\Identity\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Audit log CSV export: the list filters from the query string.
 */
class AuditExportRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:191'],
            'actor' => ['nullable', 'integer', 'min:1'],
            'tenant' => ['nullable', 'integer', 'min:1'],
            'event' => ['nullable', 'string', 'max:191'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date_from' => __('identity::audit.filters.from'),
            'date_to' => __('identity::audit.filters.to'),
        ];
    }
}
