<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Modules\Platform\DTOs\DashboardPeriodData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dashboard filters: period (?period_from=&period_to=, date range picker) and ?country_id=.
 */
class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'period_from' => ['nullable', 'date_format:Y-m-d'],
            'period_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:period_from'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
        ];
    }

    public function period(): DashboardPeriodData
    {
        $from = $this->validated('period_from');
        $to = $this->validated('period_to');

        return new DashboardPeriodData(is_string($from) ? $from : null, is_string($to) ? $to : null, $this->integer('country_id') ?: null);
    }
}
