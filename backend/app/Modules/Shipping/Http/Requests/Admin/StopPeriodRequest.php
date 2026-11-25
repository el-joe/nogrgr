<?php

namespace App\Modules\Shipping\Http\Requests\Admin;

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Enums\StopPeriodRecurrence;
use App\Modules\Shipping\Enums\Weekday;
use App\Modules\Shipping\Models\ShippingCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Stop period create / edit (ADM-033c): name, company / country (empty = all), a date
 * range or a weekly weekday, active.
 */
class StopPeriodRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'shipping_company_id' => ['nullable', 'integer', Rule::exists(ShippingCompany::class, 'id')],
            'country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
            'recurrence' => ['required', Rule::enum(StopPeriodRecurrence::class)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'weekday' => ['nullable', 'required_if:recurrence,'.StopPeriodRecurrence::Weekly->value, Rule::enum(Weekday::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('shipping::stop_periods.fields.name'),
            'shipping_company_id' => __('shipping::stop_periods.fields.company'),
            'country_id' => __('shipping::stop_periods.fields.country'),
            'recurrence' => __('shipping::stop_periods.fields.recurrence'),
            'starts_on' => __('shipping::stop_periods.fields.starts_on'),
            'ends_on' => __('shipping::stop_periods.fields.ends_on'),
            'weekday' => __('shipping::stop_periods.fields.weekday'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $recurrence = StopPeriodRecurrence::from((string) $this->validated('recurrence'));

        return [
            'name' => trim((string) $this->validated('name')),
            'shipping_company_id' => $this->filled('shipping_company_id') ? (int) $this->validated('shipping_company_id') : null,
            'country_id' => $this->filled('country_id') ? (int) $this->validated('country_id') : null,
            'recurrence' => $recurrence,
            'starts_on' => (string) $this->validated('starts_on'),
            'ends_on' => $this->filled('ends_on') ? (string) $this->validated('ends_on') : null,
            'weekday' => $recurrence === StopPeriodRecurrence::Weekly ? (int) $this->validated('weekday') : null,
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
