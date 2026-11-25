<?php

namespace App\Modules\Shipping\Http\Requests\Admin;

use App\Modules\Shipping\Enums\Weekday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shipping settings (ADM-034): default min / max days, cut-off time, weekly off days, toggles.
 */
class ShippingSettingsRequest extends FormRequest
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
            'default_min_days' => ['required', 'integer', 'min:0', 'max:60'],
            'default_max_days' => ['required', 'integer', 'min:1', 'max:90', 'gte:default_min_days'],
            'cutoff_time' => ['nullable', 'date_format:H:i'],
            'weekly_off_days' => ['nullable', 'array', 'max:6'],
            'weekly_off_days.*' => ['distinct', Rule::enum(Weekday::class)],
            'show_estimate_on_product' => ['required', 'boolean'],
            'show_delivery_popup' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'default_min_days' => __('shipping::settings.fields.default_min_days'),
            'default_max_days' => __('shipping::settings.fields.default_max_days'),
            'cutoff_time' => __('shipping::settings.fields.cutoff_time'),
            'weekly_off_days' => __('shipping::settings.fields.weekly_off_days'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'default_min_days' => (int) $this->validated('default_min_days'),
            'default_max_days' => (int) $this->validated('default_max_days'),
            'cutoff_time' => $this->filled('cutoff_time') ? (string) $this->validated('cutoff_time') : null,
            'weekly_off_days' => array_values(array_map('intval', (array) ($this->validated('weekly_off_days') ?? []))),
            'show_estimate_on_product' => $this->boolean('show_estimate_on_product'),
            'show_delivery_popup' => $this->boolean('show_delivery_popup'),
        ];
    }
}
