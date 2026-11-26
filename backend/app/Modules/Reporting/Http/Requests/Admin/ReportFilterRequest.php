<?php

namespace App\Modules\Reporting\Http\Requests\Admin;

use App\Modules\Reporting\DTOs\ReportFilterData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Report / dashboard filters: ?from=&to= (inclusive days), ?tenant_id=, ?country_id=.
 * The window is capped at 3 years.
 */
class ReportFilterRequest extends FormRequest
{
    public const MAX_DAYS = 1096;

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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'tenant_id' => ['nullable', 'integer', Rule::exists('tenants', 'id')],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [function ($validator): void {
            $from = $this->input('from');
            $to = $this->input('to');

            if (is_string($from) && is_string($to) && strtotime($from) !== false && strtotime($to) !== false
                && (strtotime($to) - strtotime($from)) / 86400 >= self::MAX_DAYS) {
                $validator->errors()->add('from', __('reporting::reports.validation.window', ['days' => self::MAX_DAYS]));
            }
        }];
    }

    public function filters(): ReportFilterData
    {
        return ReportFilterData::fromInput($this->validated());
    }
}
