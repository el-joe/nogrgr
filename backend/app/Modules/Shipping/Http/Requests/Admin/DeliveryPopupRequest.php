<?php

namespace App\Modules\Shipping\Http\Requests\Admin;

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Services\DeliveryPopupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Delivery popup days (ADM-035): integers 0–100 for days 1–10, total exactly 100 %.
 * country_id empty = the distribution for all countries.
 */
class DeliveryPopupRequest extends FormRequest
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
            'country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
            'days' => ['required', 'array', 'size:'.DeliveryPopupService::DAYS],
            'days.*' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $keys = array_map('intval', array_keys((array) $this->input('days')));
            sort($keys);

            if ($keys !== range(1, DeliveryPopupService::DAYS)) {
                $validator->errors()->add('days', __('shipping::popup.days_invalid'));

                return;
            }

            $total = array_sum($this->percentages());

            if ($total !== DeliveryPopupService::TOTAL) {
                $validator->errors()->add('days', __('shipping::popup.sum_invalid', ['total' => $total]));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['days.*' => __('shipping::popup.percentage')];
    }

    public function countryId(): ?int
    {
        return $this->filled('country_id') ? (int) $this->input('country_id') : null;
    }

    /**
     * @return array<int, int>
     */
    public function percentages(): array
    {
        $days = [];

        foreach ((array) $this->input('days') as $day => $value) {
            $days[(int) $day] = (int) $value;
        }

        ksort($days);

        return $days;
    }
}
